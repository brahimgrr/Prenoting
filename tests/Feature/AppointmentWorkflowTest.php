<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_book_generated_slot_start(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();

        $response = $this->actingAs($patientUser)->post('/appointments', [
            'slot_start' => $slot->key,
            'service_id' => $service->id,
            'notes' => 'Prima visita',
        ]);

        $response->assertRedirect('/patient/appointments');
        $appointment = Appointment::firstOrFail();
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame($doctor->id, $appointment->service->doctor_profile_id);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($slot->key, $appointment->start_at->format('Y-m-d\TH:i'));
    }

    public function test_patient_cannot_book_appointment_within_24_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-10 09:00:00'));

        try {
            [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
            $slot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));

            $response = $this->actingAs($patientUser)
                ->from('/patient/book')
                ->post('/appointments', [
                    'slot_start' => $slot->key,
                    'service_id' => $service->id,
                ]);

            $response->assertRedirect('/patient/book');
            $response->assertSessionHasErrors('slot_start');
            $this->assertSame(0, Appointment::count());
        } finally {
            $this->travelBack();
        }
    }

    public function test_booking_page_renders_service_week_days_and_generated_slots(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();

        $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

        $response->assertOk();
        $response->assertSee('Scegli la prestazione');
        $response->assertSee('class="service-choice-card d-grid gap-2 h-100 p-3 border-primary bg-primary bg-opacity-10" href=', false);
        $response->assertSee('Scegli il giorno');
        $response->assertSee('week-strip d-flex gap-2 flex-fill overflow-auto p-1', false);
        $response->assertSee('week-day d-flex flex-column align-items-center justify-content-center gap-1 text-center p-2 week-day--selected border-primary bg-primary bg-opacity-10', false);
        $response->assertSee("Scegli l'orario", false);
        $response->assertSee($service->name);
        $response->assertSee($slot->start_at->locale('it')->isoFormat('D MMM'), false);
        $response->assertSee($slot->start_at->format('H:i'));
        $response->assertSee('slot liberi');
        $response->assertDontSee('slot_id');
    }

    public function test_booking_page_can_jump_to_a_far_month_with_availability(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
        $farStart = CarbonImmutable::now()->addMonths(3)->startOfMonth()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
        $this->slotAt($doctor, $farStart);
        $month = $farStart->format('Y-m');

        $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

        $response->assertOk();
        $response->assertSee('<strong>'.$farStart->format('d').'</strong>', false);
        $response->assertSee('<span class="week-day__month">'.ucfirst($farStart->locale('it')->isoFormat('MMM')).'</span>', false);
        $response->assertDontSee('month-jump-select');
    }

    public function test_booking_page_handles_services_with_no_available_slots(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);

        $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

        $response->assertOk();
        $response->assertSee('Nessuna disponibilita per questa prestazione');
        $response->assertDontSee('Trying to access array offset on null');
    }

    public function test_booking_period_filter_shows_only_matching_slots(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
        $date = CarbonImmutable::now()->addDays(2)->startOfDay();
        $morningSlot = $this->slotAt($doctor, $date->setTime(9, 0));
        $afternoonSlot = $this->slotAt($doctor, $date->setTime(15, 0));
        $weekStart = $date->toDateString();

        $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date->toDateString()}&period=mattina");

        $response->assertOk();
        $response->assertSee($morningSlot->start_at->format('H:i'));
        $response->assertDontSee($afternoonSlot->start_at->format('H:i'));
        $response->assertSee('period=mattina', false);
    }

    public function test_second_booking_attempt_for_same_generated_start_fails(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $otherUser = $this->patient('other-patient@example.com')[0];

        $this->actingAs($patientUser)->post('/appointments', [
            'slot_start' => $slot->key,
            'service_id' => $service->id,
        ]);

        $response = $this->actingAs($otherUser)->from('/patient/book')->post('/appointments', [
            'slot_start' => $slot->key,
            'service_id' => $service->id,
        ]);

        $response->assertRedirect('/patient/book');
        $response->assertSessionHasErrors('slot_start');
        $this->assertSame(1, Appointment::count());
    }

    public function test_patient_can_cancel_future_confirmed_appointment_and_time_becomes_available_again(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $appointment = $this->appointment($patient, $doctor, $service, $slot);

        $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Non posso partecipare',
        ]);

        $response->assertRedirect('/patient/appointments');
        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->status);
        $this->assertSame(Appointment::CANCELLED_BY_PATIENT, $appointment->cancelled_by_role);
        $this->assertSame($patientUser->id, $appointment->cancelled_by_user_id);
        $this->assertNotNull($appointment->cancelled_at);
        $available = app(AvailabilityService::class)->availableSlotByKey($doctor, $service, $slot->key);
        $this->assertNotNull($available);
    }

    public function test_patient_cannot_cancel_appointment_within_24_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-10 09:00:00'));

        try {
            [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
            $slot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
            $appointment = $this->appointment($patient, $doctor, $service, $slot);

            $response = $this->actingAs($patientUser)
                ->from('/patient/appointments')
                ->post("/appointments/{$appointment->id}/cancel");

            $response->assertRedirect('/patient/appointments');
            $response->assertSessionHasErrors('start_at');
            $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->fresh()->status);
        } finally {
            $this->travelBack();
        }
    }

    public function test_patient_can_reschedule_to_different_available_generated_start(): void
    {
        [$patientUser, $patient, $doctor, $service, $oldSlot] = $this->bookingContext();
        $newSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDays(2)->setTime(10, 0));
        $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);

        $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/reschedule", [
            'slot_start' => $newSlot->key,
        ]);

        $response->assertRedirect('/patient/appointments');
        $appointment->refresh();
        $this->assertSame($newSlot->key, $appointment->start_at->format('Y-m-d\TH:i'));
        $this->assertNotNull(app(AvailabilityService::class)->availableSlotByKey($doctor, $service, $oldSlot->key));
        $this->assertNull(app(AvailabilityService::class)->availableSlotByKey($doctor, $service, $newSlot->key));
    }

    public function test_patient_cannot_reschedule_appointment_within_24_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-10 09:00:00'));

        try {
            [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
            $oldSlot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
            $newSlot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-12 10:00:00'));
            $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);

            $response = $this->actingAs($patientUser)
                ->from('/patient/appointments')
                ->post("/appointments/{$appointment->id}/reschedule", [
                    'slot_start' => $newSlot->key,
                ]);

            $response->assertRedirect('/patient/appointments');
            $response->assertSessionHasErrors('start_at');
            $this->assertSame($oldSlot->key, $appointment->fresh()->start_at->format('Y-m-d\TH:i'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_selected_booking_slot_renders_confirmation_before_posting(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $anchor = $slot->start_at->toDateString();

        $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$anchor}&date={$slot->start_at->toDateString()}&slot_start={$slot->key}");

        $response->assertOk();
        $response->assertSee('Conferma prenotazione');
        $response->assertSee('name="slot_start" value="'.$slot->key.'"', false);
        $response->assertSee('slot-time-button d-grid align-items-center justify-content-center w-100 p-2 slot-time-button--selected border-primary bg-primary bg-opacity-10', false);
        $response->assertSee('action="/appointments"', false);
        $response->assertSee($slot->start_at->format('H:i').' - '.$slot->end_at->format('H:i'));
        $response->assertDontSee('Medico');
        $response->assertDontSee('Ambulatorio');
    }

    public function test_selected_booking_slot_ajax_request_returns_booking_wizard_partial(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $anchor = $slot->start_at->toDateString();

        $response = $this->actingAs($patientUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get("/patient/book?service_id={$service->id}&week_start={$anchor}&date={$slot->start_at->toDateString()}&slot_start={$slot->key}");

        $response->assertOk();
        $response->assertSee('class="booking-wizard', false);
        $response->assertSee('Conferma prenotazione');
        $response->assertSee('name="slot_start" value="'.$slot->key.'"', false);
        $response->assertDontSee('<!doctype html>', false);
        $response->assertDontSee('<main class="app-content', false);
    }

    public function test_patient_appointments_page_renders_icon_actions_and_cancel_modal(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $doctor->forceFill([
            'license_number' => 'DERM-001',
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1',
        ])->save();
        $doctor->user->forceFill([
            'first_name' => 'Kylian',
            'last_name' => 'Mbappe',
        ])->save();
        $appointment = $this->appointment($patient, $doctor, $service, $slot);
        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(2)->setTime(9, 0),
            CarbonImmutable::now()->subDays(2)->setTime(9, 30),
            $doctor->id,
        );
        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED);

        $response = $this->actingAs($patientUser)->get('/patient/appointments');

        $response->assertOk();
        $response->assertSee('<h3 class="mb-0">'.$service->name.'</h3>', false);
        $response->assertSee('<dt>Orario</dt>', false);
        $response->assertSee('<dd>'.$appointment->start_at->format('d/m/Y H:i').' - '.$appointment->end_at->format('H:i').'</dd>', false);
        $response->assertDontSee('aria-label="Azioni appuntamento"', false);
        $response->assertSee('aria-label="Informazioni medico"', false);
        $response->assertSee('<i class="bi bi-info-circle"', false);
        $response->assertSee("data-bs-target=\"#doctorInfoModal{$appointment->id}\"", false);
        $response->assertSee('aria-label="Sposta appuntamento"', false);
        $response->assertSee('<i class="bi bi-pencil-square"', false);
        $response->assertSee("href=\"/appointments/{$appointment->id}/edit\"", false);
        $response->assertSee('aria-label="Annulla appuntamento"', false);
        $response->assertSee('<i class="bi bi-trash"', false);
        $response->assertSee("id=\"doctorInfoModal{$appointment->id}\"", false);
        $response->assertSeeText('Informazioni medico');
        $response->assertSeeText('Dott. Mbappe');
        $response->assertSeeText('doctor.derm@example.com');
        $response->assertSeeText('3331000000');
        $response->assertSeeText('Via Roma 1');
        $response->assertSeeText('DERM-001');
        $response->assertDontSeeText('Bio');
        $response->assertDontSeeText('Dermatologo specializzato in prevenzione.');
        $response->assertSee("id=\"appointmentCancelModal{$appointment->id}\"", false);
        $response->assertSee("action=\"/appointments/{$appointment->id}/cancel\"", false);
        $response->assertDontSee('<dt>Medico</dt>', false);
        $response->assertDontSeeText('Ambulatorio');
    }

    public function test_patient_appointments_page_hides_empty_note_rows(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $this->appointment($patient, $doctor, $service, $slot);

        $response = $this->actingAs($patientUser)->get('/patient/appointments');

        $response->assertOk();
        $response->assertDontSee('<dt>Note</dt>', false);
        $response->assertDontSee('Nessuna nota');
    }

    public function test_patient_appointments_page_hides_change_actions_within_24_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-10 09:00:00'));

        try {
            [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
            $slot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
            $this->appointment($patient, $doctor, $service, $slot);

            $response = $this->actingAs($patientUser)->get('/patient/appointments');

            $response->assertOk();
            $response->assertDontSee('Sposta appuntamento');
            $response->assertDontSee('Annulla appuntamento');
            $response->assertSee('Modifiche e cancellazioni non disponibili nelle 24 ore precedenti');
        } finally {
            $this->travelBack();
        }
    }

    public function test_patient_appointments_page_collapses_history_until_requested(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);

        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Storico pesante');

        $response = $this->actingAs($patientUser)->get('/patient/appointments');

        $response->assertOk();
        $response->assertSee('Visualizza storico appuntamenti');
        $response->assertSee('data-appointments-history-placeholder', false);
        $response->assertDontSee('<section class="appointment-group mt-4" data-appointments-history>', false);
        $response->assertDontSeeText('Storico pesante');
        $response->assertDontSee('Nascondi storico appuntamenti');
    }

    public function test_patient_appointments_history_defaults_to_past_when_opened(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);

        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $patientCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Solo passato predefinito');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $patientCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Solo annullato nascosto',
            Appointment::CANCELLED_BY_PATIENT,
        );

        $response = $this->actingAs($patientUser)->get('/patient/appointments?show_history=1');

        $response->assertOk();
        $response->assertSee('data-appointments-history', false);
        $response->assertSee('Nascondi storico appuntamenti');
        $response->assertSee('aria-current="page"', false);
        $response->assertSeeText('Solo passato predefinito');
        $response->assertDontSeeText('Solo annullato nascosto');

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Annullati da te'), strpos($content, 'Passati'));
        $this->assertLessThan(strpos($content, 'Annullati dal medico'), strpos($content, 'Annullati da te'));
        $this->assertLessThan(strpos($content, 'Tutti'), strpos($content, 'Annullati dal medico'));
    }

    public function test_patient_appointments_history_marks_past_and_cancelled_and_shows_visit_details(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
        $service->forceFill(['price' => '95.50'])->save();

        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $cancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );
        $doctorCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(5)->setTime(12, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(5)->setTime(12, 0),
            CarbonImmutable::now()->addDays(5)->setTime(12, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Controllo nei prossimi mesi');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $cancelledSlot,
            Appointment::STATUS_CANCELLED,
            'Portare referti precedenti',
            'Imprevisto personale',
            Appointment::CANCELLED_BY_PATIENT,
        );
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $doctorCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Indisponibilita del medico',
            Appointment::CANCELLED_BY_DOCTOR,
        );

        $response = $this->actingAs($patientUser)->get('/patient/appointments?show_history=1&history_filter=all');

        $response->assertOk();
        $response->assertSeeText('Passato');
        $response->assertSeeText('Annullato da te');
        $response->assertSeeText('Annullato dal medico');
        $response->assertSeeText('EUR 95,50');
        $response->assertSeeText('Controllo nei prossimi mesi');
        $response->assertSeeText('Portare referti precedenti');
        $response->assertSeeText('Imprevisto personale');
        $response->assertSeeText('Indisponibilita del medico');
        $response->assertDontSee('<dt>Annullamento</dt>', false);
        $response->assertSee('<dt>Motivo annullamento</dt>', false);
    }

    public function test_patient_appointments_history_can_be_filtered_by_past_and_cancellation_actor(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);

        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $patientCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );
        $doctorCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(5)->setTime(12, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(5)->setTime(12, 0),
            CarbonImmutable::now()->addDays(5)->setTime(12, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Solo appuntamento passato');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $patientCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Solo annullamento paziente',
            Appointment::CANCELLED_BY_PATIENT,
        );
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $doctorCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Solo annullamento medico',
            Appointment::CANCELLED_BY_DOCTOR,
        );

        $this->actingAs($patientUser)
            ->get('/patient/appointments?show_history=1&history_filter=all')
            ->assertOk()
            ->assertSee('Tutti')
            ->assertSee('Passati')
            ->assertSee('Annullati da te')
            ->assertSee('Annullati dal medico')
            ->assertSeeText('Solo appuntamento passato')
            ->assertSeeText('Solo annullamento paziente')
            ->assertSeeText('Solo annullamento medico');

        $this->actingAs($patientUser)
            ->get('/patient/appointments?show_history=1&history_filter=past')
            ->assertOk()
            ->assertSeeText('Solo appuntamento passato')
            ->assertDontSeeText('Solo annullamento paziente')
            ->assertDontSeeText('Solo annullamento medico');

        $this->actingAs($patientUser)
            ->get('/patient/appointments?show_history=1&history_filter=cancelled_by_patient')
            ->assertOk()
            ->assertDontSeeText('Solo appuntamento passato')
            ->assertSeeText('Solo annullamento paziente')
            ->assertDontSeeText('Solo annullamento medico');

        $this->actingAs($patientUser)
            ->get('/patient/appointments?show_history=1&history_filter=cancelled_by_doctor')
            ->assertOk()
            ->assertDontSeeText('Solo appuntamento passato')
            ->assertDontSeeText('Solo annullamento paziente')
            ->assertSeeText('Solo annullamento medico');
    }

    public function test_patient_appointments_history_filter_ajax_request_returns_history_partial(): void
    {
        [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);

        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $patientCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Storico passato');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $patientCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Storico annullato dal paziente',
            Appointment::CANCELLED_BY_PATIENT,
        );

        $this->actingAs($patientUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/patient/appointments?show_history=1&history_filter=cancelled_by_patient')
            ->assertOk()
            ->assertSee('data-appointments-history', false)
            ->assertSeeText('Storico annullato dal paziente')
            ->assertDontSeeText('Storico passato')
            ->assertDontSee('<!doctype html>', false)
            ->assertDontSee('<main class="app-content', false);
    }

    public function test_doctor_appointments_page_shows_current_appointments_with_cancel_action(): void
    {
        [, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $patient->forceFill([
            'date_of_birth' => '1990-05-12',
            'place_of_birth' => 'Milano',
            'gender' => 'M',
            'address' => 'Via Roma 10',
            'codice_fiscale' => 'RSSMRA90E12F205X',
        ])->save();
        $appointment = $this->appointment($patient, $doctor, $service, $slot, notes: 'Portare referti');

        $response = $this->actingAs($doctor->user)->get('/doctor/appointments');

        $response->assertOk();
        $response->assertSeeText('Appuntamenti');
        $response->assertSeeText('Imminenti');
        $response->assertSee('<h3 class="mb-0">'.$patient->displayName().'</h3>', false);
        $response->assertDontSee('<h3 class="mb-0">'.$service->name.'</h3>', false);
        $response->assertSeeText($service->name);
        $response->assertSeeText('Portare referti');
        $response->assertDontSee('<dt>Telefono paziente</dt>', false);
        $response->assertDontSee('<dt>Email paziente</dt>', false);
        $response->assertDontSee('aria-label="Azioni appuntamento"', false);
        $response->assertSee('aria-label="Informazioni paziente"', false);
        $response->assertSee('<i class="bi bi-info-circle"', false);
        $response->assertSee("data-bs-target=\"#patientInfoModal{$appointment->id}\"", false);
        $response->assertSee("id=\"patientInfoModal{$appointment->id}\"", false);
        $response->assertSeeText($patient->displayName());
        $response->assertSeeText('555-0100');
        $response->assertSeeText('patient@example.com');
        $response->assertSeeText('12/05/1990');
        $response->assertSeeText('Milano');
        $response->assertSeeText('M');
        $response->assertSeeText('Via Roma 10');
        $response->assertSeeText('RSSMRA90E12F205X');
        $response->assertSee('aria-label="Annulla appuntamento"', false);
        $response->assertSee('<i class="bi bi-trash"', false);
        $response->assertSee("id=\"appointmentCancelModal{$appointment->id}\"", false);
        $response->assertSee("action=\"/doctor/appointments/{$appointment->id}/cancel\"", false);
        $response->assertSee('href="/doctor/appointments?show_history=1"', false);
    }

    public function test_doctor_appointments_history_can_be_filtered_by_past_and_cancellation_actor(): void
    {
        [, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $patientCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );
        $doctorCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(5)->setTime(12, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(5)->setTime(12, 0),
            CarbonImmutable::now()->addDays(5)->setTime(12, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Solo appuntamento passato');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $patientCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Solo annullamento paziente',
            Appointment::CANCELLED_BY_PATIENT,
        );
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $doctorCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Solo annullamento medico',
            Appointment::CANCELLED_BY_DOCTOR,
        );

        $this->actingAs($doctor->user)
            ->get('/doctor/appointments?show_history=1&history_filter=all')
            ->assertOk()
            ->assertSee('Tutti')
            ->assertSee('Passati')
            ->assertSee('Annullati dal paziente')
            ->assertSee('Annullati da te')
            ->assertSeeText('Solo appuntamento passato')
            ->assertSeeText('Solo annullamento paziente')
            ->assertSeeText('Solo annullamento medico');

        $this->actingAs($doctor->user)
            ->get('/doctor/appointments?show_history=1&history_filter=past')
            ->assertOk()
            ->assertSeeText('Solo appuntamento passato')
            ->assertDontSeeText('Solo annullamento paziente')
            ->assertDontSeeText('Solo annullamento medico');

        $this->actingAs($doctor->user)
            ->get('/doctor/appointments?show_history=1&history_filter=cancelled_by_patient')
            ->assertOk()
            ->assertDontSeeText('Solo appuntamento passato')
            ->assertSeeText('Solo annullamento paziente')
            ->assertDontSeeText('Solo annullamento medico');

        $this->actingAs($doctor->user)
            ->get('/doctor/appointments?show_history=1&history_filter=cancelled_by_doctor')
            ->assertOk()
            ->assertDontSeeText('Solo appuntamento passato')
            ->assertDontSeeText('Solo annullamento paziente')
            ->assertSeeText('Solo annullamento medico');
    }

    public function test_doctor_appointments_history_filter_ajax_request_returns_history_partial(): void
    {
        [, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
        $pastSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->subDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->subDays(3)->setTime(9, 0),
            CarbonImmutable::now()->subDays(3)->setTime(9, 30),
            $doctor->id,
        );
        $patientCancelledSlot = new VirtualAvailabilitySlot(
            CarbonImmutable::now()->addDays(4)->setTime(11, 0)->format('Y-m-d\TH:i'),
            CarbonImmutable::now()->addDays(4)->setTime(11, 0),
            CarbonImmutable::now()->addDays(4)->setTime(11, 30),
            $doctor->id,
        );

        $this->appointment($patient, $doctor, $service, $pastSlot, Appointment::STATUS_COMPLETED, 'Storico passato medico');
        $this->appointment(
            $patient,
            $doctor,
            $service,
            $patientCancelledSlot,
            Appointment::STATUS_CANCELLED,
            '',
            'Storico annullato dal paziente',
            Appointment::CANCELLED_BY_PATIENT,
        );

        $this->actingAs($doctor->user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/doctor/appointments?show_history=1&history_filter=cancelled_by_patient')
            ->assertOk()
            ->assertSee('data-appointments-history', false)
            ->assertSeeText('Storico annullato dal paziente')
            ->assertDontSeeText('Storico passato medico')
            ->assertDontSee('<!doctype html>', false)
            ->assertDontSee('<main class="app-content', false);
    }

    public function test_doctor_can_cancel_future_active_appointment_from_appointments_page(): void
    {
        [, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $appointment = $this->appointment($patient, $doctor, $service, $slot);

        $this->actingAs($doctor->user)
            ->from('/doctor/appointments')
            ->post("/doctor/appointments/{$appointment->id}/cancel", [
                'cancellation_reason' => 'Cambio disponibilita',
            ])
            ->assertRedirect('/doctor/appointments')
            ->assertSessionHas('status', 'Appuntamento annullato.');

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->status);
        $this->assertSame(Appointment::CANCELLED_BY_DOCTOR, $appointment->cancelled_by_role);
        $this->assertSame($doctor->user->id, $appointment->cancelled_by_user_id);
        $this->assertSame('Cambio disponibilita', $appointment->cancellation_reason);
        $this->assertNotNull($appointment->cancelled_at);
    }

    public function test_patient_can_open_reschedule_wizard_and_confirm_new_start(): void
    {
        [$patientUser, $patient, $doctor, $service, $oldSlot] = $this->bookingContext();
        $newSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDays(2)->setTime(10, 0));
        $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);
        $weekStart = $newSlot->start_at->toDateString();

        $response = $this->actingAs($patientUser)->get("/appointments/{$appointment->id}/edit?week_start={$weekStart}&date={$newSlot->start_at->toDateString()}&slot_start={$newSlot->key}");

        $response->assertOk();
        $response->assertSee('Stai riprogrammando');
        $response->assertSee($service->name);
        $response->assertSee('Prestazione selezionata');
        $response->assertSee('class="service-choice-card d-grid gap-2 h-100 p-3 border-primary bg-primary bg-opacity-10"', false);
        $response->assertDontSee('service-choice-card--disabled');
        $response->assertDontSee('href="/appointments/'.$appointment->id.'/edit?service_id=', false);
        $response->assertSee($newSlot->start_at->format('H:i'));
        $response->assertSee('Conferma spostamento');
        $response->assertSee("/appointments/{$appointment->id}/reschedule", false);
        $response->assertSee('<a class="btn btn-outline-secondary" href="/patient/appointments">', false);
        $response->assertSee('Annulla spostamento');
    }

    public function test_patient_can_navigate_reschedule_wizard_weeks(): void
    {
        [$patientUser, $patient, $doctor, $service, $oldSlot] = $this->bookingContext();
        for ($daysFromNow = 3; $daysFromNow <= 7; $daysFromNow++) {
            $this->slotAt($doctor, CarbonImmutable::now()->addDays($daysFromNow)->setTime(10, 0));
        }
        $farSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDays(8)->setTime(10, 0));
        $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);
        $nextWeekStart = CarbonImmutable::now()->addDays(7)->startOfDay()->toDateString();

        $response = $this->actingAs($patientUser)->get("/appointments/{$appointment->id}/edit");

        $response->assertOk();
        $response->assertSee('Settimana successiva');
        $response->assertSee("/appointments/{$appointment->id}/edit/week?service_id={$service->id}&amp;week_start={$nextWeekStart}", false);
        $response->assertSee("/appointments/{$appointment->id}/edit?service_id={$service->id}&amp;week_start={$nextWeekStart}#booking-step-day", false);

        $partial = $this->actingAs($patientUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get("/appointments/{$appointment->id}/edit/week?service_id={$service->id}&week_start={$nextWeekStart}");

        $partial->assertOk();
        $partial->assertSee($farSlot->start_at->format('H:i'));
        $partial->assertDontSee('<!doctype', false);
    }

    public function test_reschedule_slot_ajax_request_returns_booking_wizard_partial(): void
    {
        [$patientUser, $patient, $doctor, $service, $oldSlot] = $this->bookingContext();
        $newSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDays(2)->setTime(10, 0));
        $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);
        $weekStart = $newSlot->start_at->toDateString();

        $response = $this->actingAs($patientUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get("/appointments/{$appointment->id}/edit?week_start={$weekStart}&date={$newSlot->start_at->toDateString()}&slot_start={$newSlot->key}");

        $response->assertOk();
        $response->assertSee('class="booking-wizard', false);
        $response->assertSee('Conferma spostamento');
        $response->assertSee('name="slot_start" value="'.$newSlot->key.'"', false);
        $response->assertDontSee('<!doctype', false);
        $response->assertDontSee('<main class="app-content', false);
    }

    public function test_patient_cannot_open_another_patients_reschedule_wizard(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $otherUser = $this->patient('other-patient@example.com')[0];
        $appointment = $this->appointment($patient, $doctor, $service, $slot);

        $this->actingAs($otherUser)->get("/appointments/{$appointment->id}/edit")->assertNotFound();
    }

    public function test_patient_cannot_cancel_non_confirmed_or_past_appointment(): void
    {
        [$patientUser, $patient, $doctor, $service, $slot] = $this->bookingContext();
        $appointment = $this->appointment($patient, $doctor, $service, $slot, Appointment::STATUS_COMPLETED);

        $response = $this->actingAs($patientUser)->from('/patient/appointments')->post("/appointments/{$appointment->id}/cancel");

        $response->assertRedirect('/patient/appointments');
        $response->assertSessionHasErrors('status');
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    }

    private function bookingContext(bool $createSlot = true): array
    {
        [$patientUser, $patient] = $this->patient('patient@example.com');
        $doctorUser = User::create([
            'email' => 'doctor.derm@example.com',
            'password' => Hash::make('doctor123'),
        ]);
        $this->assignRole($doctorUser, User::ROLE_DOCTOR);
        $doctor = DoctorProfile::create([
            'user_id' => $doctorUser->id,
            'display_name' => 'Dott. Mbappe',
        ]);
        $service = MedicalService::create([
            'doctor_profile_id' => $doctor->id,
            'name' => 'Visita dermatologica',
        ]);
        $slot = $createSlot ? $this->slotAt($doctor, CarbonImmutable::now()->addDays(2)->setTime(9, 0)) : null;

        return array_filter([$patientUser, $patient, $doctor, $service, $slot], fn ($value) => $value !== null);
    }

    private function patient(string $email): array
    {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        $profile = PatientProfile::create(['user_id' => $user->id, 'phone' => '555-0100']);

        return [$user, $profile];
    }

    private function slotAt(DoctorProfile $doctor, CarbonImmutable $start): VirtualAvailabilitySlot
    {
        $doctor->specialOpenings()->create([
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->addMinutes(30)->format('H:i:s'),
        ]);

        return new VirtualAvailabilitySlot(
            $start->format('Y-m-d\TH:i'),
            $start,
            $start->addMinutes(30),
            $doctor->id,
        );
    }

    private function appointment(
        PatientProfile $patient,
        DoctorProfile $doctor,
        MedicalService $service,
        VirtualAvailabilitySlot $slot,
        string $status = Appointment::STATUS_CONFIRMED,
        string $notes = '',
        ?string $cancellationReason = null,
        ?string $cancelledByRole = null,
    ): Appointment {
        return Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'status' => $status,
            'notes' => $notes,
            'cancellation_reason' => $cancellationReason,
            'cancelled_by_role' => $cancelledByRole,
        ]);
    }
}
