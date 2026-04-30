<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilitySlot;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentWorkflowTest extends TestCase
{
  use RefreshDatabase;

  public function test_patient_can_book_available_slot_and_slot_is_marked_booked(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();

    $response = $this->actingAs($patientUser)->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
      'notes' => 'Prima visita',
    ]);

    $response->assertRedirect('/patient/appointments');
    $appointment = Appointment::first();
    $this->assertSame($patient->id, $appointment->patient_id);
    $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
    $this->assertTrue($slot->fresh()->is_booked);
  }

  public function test_booking_page_renders_service_week_days_and_available_slots(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

    $response->assertOk();
    $response->assertSee('Scegli la prestazione');
    $response->assertSee('Scegli il giorno');
    $response->assertSee("Scegli l'orario", false);
    $response->assertDontSee('Mese visualizzato');
    $response->assertDontSee('Mese precedente');
    $response->assertDontSee('Mese successivo');
    $response->assertSee($service->name);
    $response->assertSee($slot->start_at->format('H:i'));
    $response->assertSee('slot liberi');
  }

  public function test_booking_page_can_jump_to_a_far_month_with_availability(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $farStart = CarbonImmutable::now()->addMonths(3)->startOfMonth()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $farSlot = AvailabilitySlot::create([
      'start_at' => $farStart,
      'end_at' => $farStart->addMinutes(30),
    ]);
    $month = $farSlot->start_at->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee(ucfirst($farSlot->start_at->locale('it')->isoFormat('MMMM YYYY')));
    $response->assertSee($farSlot->start_at->format('d'));
    $response->assertSee('month='.$month, false);
  }

  public function test_booking_month_selector_contains_direct_navigation_fallback(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $month = $slot->start_at->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee('onchange="window.location.href=this.value"', false);
  }

  public function test_booking_week_arrows_render_direct_navigation_links(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    AvailabilitySlot::query()->delete();
    $starts = collect([
      CarbonImmutable::create(2030, 4, 27, 9, 0),
      CarbonImmutable::create(2030, 4, 28, 9, 0),
      CarbonImmutable::create(2030, 4, 29, 9, 0),
      CarbonImmutable::create(2030, 4, 30, 9, 0),
      CarbonImmutable::create(2030, 5, 1, 9, 0),
      CarbonImmutable::create(2030, 5, 2, 9, 0),
    ]);

    $starts->each(function (CarbonImmutable $start): void {
      AvailabilitySlot::create([
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
      ]);
    });

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

    $response->assertOk();
    $response->assertSee('Aprile 2030');
    $response->assertDontSee('Mese visualizzato');
    $response->assertSee("href=\"http://127.0.0.1:8080/patient/book?service_id={$service->id}&amp;week_start=2030-05-02#booking-step-day\"", false);

    $nextResponse = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start=2030-05-02");

    $nextResponse->assertOk();
    $nextResponse->assertSee('Maggio 2030');
    $nextResponse->assertSee('value="http://127.0.0.1:8080/patient/book?service_id='.$service->id.'&amp;month=2030-05#booking-step-day"', false);
  }

  public function test_booking_calendar_starts_from_nearest_available_date_and_shows_only_available_days(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $nearest = CarbonImmutable::now()->addDays(2)->setTime(9, 0);
    $later = CarbonImmutable::now()->addDays(5)->setTime(11, 0);
    AvailabilitySlot::query()->delete();
    AvailabilitySlot::create([
      'start_at' => $nearest,
      'end_at' => $nearest->addMinutes(30),
    ]);
    AvailabilitySlot::create([
      'start_at' => $later,
      'end_at' => $later->addMinutes(30),
    ]);

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

    $response->assertOk();
    $response->assertSee('data-date="'.$nearest->toDateString().'"', false);
    $response->assertSee('data-date="'.$later->toDateString().'"', false);
    $response->assertDontSee('data-date="'.CarbonImmutable::now()->addDay()->toDateString().'"', false);
  }

  public function test_booking_page_handles_services_with_no_available_slots(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    AvailabilitySlot::query()->delete();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}");

    $response->assertOk();
    $response->assertSee('Nessuna disponibilita per questa prestazione');
    $response->assertDontSee('Trying to access array offset on null');
  }

  public function test_booking_period_filter_shows_only_matching_slots(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $date = CarbonImmutable::parse($slot->start_at)->startOfDay()->addDay();
    $morningSlot = AvailabilitySlot::create([
      'start_at' => $date->setTime(9, 0),
      'end_at' => $date->setTime(9, 30),
    ]);
    $afternoonSlot = AvailabilitySlot::create([
      'start_at' => $date->setTime(15, 0),
      'end_at' => $date->setTime(15, 30),
    ]);
    $weekStart = $date->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date->toDateString()}&period=mattina");

    $response->assertOk();
    $response->assertSee($morningSlot->start_at->format('H:i'));
    $response->assertDontSee($afternoonSlot->start_at->format('H:i'));
    $response->assertSee('period=mattina', false);
  }

  public function test_booking_period_filter_keeps_controls_visible_when_no_slots_match(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    AvailabilitySlot::query()->delete();
    $date = CarbonImmutable::now()->addDay()->startOfDay();
    AvailabilitySlot::create([
      'start_at' => $date->setTime(15, 0),
      'end_at' => $date->setTime(15, 30),
    ]);
    $weekStart = $date->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date->toDateString()}&period=mattina");

    $response->assertOk();
    $response->assertSee('Filtra orari');
    $response->assertSee('Nessuno slot disponibile');
    $response->assertSee('Mattina');
    $response->assertSee('Pomeriggio');
  }

  public function test_booking_month_jump_shows_days_from_selected_month_instead_of_previous_month(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $targetStart = CarbonImmutable::create(2030, 5, 1, 9, 0);
    AvailabilitySlot::create([
      'start_at' => $targetStart,
      'end_at' => $targetStart->addMinutes(30),
    ]);
    $month = $targetStart->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee('data-date="2030-05-01"', false);
    $response->assertDontSee('data-date="2030-04-29"', false);
  }

  public function test_second_booking_attempt_for_same_slot_fails(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $otherUser = $this->patient('other-patient')[0];

    $this->actingAs($patientUser)->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
    ]);

    $response = $this->actingAs($otherUser)->from('/patient/book')->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
    ]);

    $response->assertRedirect('/patient/book');
    $response->assertSessionHasErrors('slot_id');
    $this->assertSame(1, Appointment::count());
  }

  public function test_patient_can_cancel_future_confirmed_appointment_and_free_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);

    $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/cancel", [
      'cancellation_reason' => 'Non posso partecipare',
    ]);

    $response->assertRedirect('/patient/appointments');
    $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    $this->assertFalse($slot->fresh()->is_booked);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'previous_status' => Appointment::STATUS_CONFIRMED,
      'new_status' => Appointment::STATUS_CANCELLED,
      'changed_by' => $patientUser->id,
    ]);
  }

  public function test_patient_can_reschedule_to_different_available_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $oldSlot] = $this->bookingContext();
    $newSlot = $this->slot($doctor, $clinic, 30);
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $oldSlot);

    $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/reschedule", [
      'slot_id' => $newSlot->id,
    ]);

    $response->assertRedirect('/patient/appointments');
    $appointment->refresh();
    $this->assertSame($newSlot->id, $appointment->slot_id);
    $this->assertFalse($oldSlot->fresh()->is_booked);
    $this->assertTrue($newSlot->fresh()->is_booked);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'new_status' => 'rescheduled',
    ]);
  }

  public function test_selected_booking_slot_renders_confirmation_before_posting(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $anchor = $slot->start_at->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$anchor}&date={$slot->start_at->toDateString()}&slot_id={$slot->id}");

    $response->assertOk();
    $response->assertSee('Conferma prenotazione');
    $response->assertSee('name="slot_id" value="'.$slot->id.'"', false);
    $response->assertSee('action="/appointments"', false);
    $response->assertSee('Cambia selezione');
    $response->assertSee('#booking-step-service', false);
    $response->assertSee('#booking-confirm', false);
    $response->assertSee('Prestazione');
    $response->assertSee('Data e ora');
    $response->assertDontSee('Medico');
    $response->assertDontSee('Ambulatorio');
    $response->assertDontSee('<button type="submit" class="slot-time-button"', false);
  }

  public function test_booking_links_keep_user_on_relevant_step_when_choosing_day_and_time(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $weekStart = $slot->start_at->toDateString();
    $date = $slot->start_at->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date}");

    $response->assertOk();
    $response->assertSee("href=\"http://127.0.0.1:8080/patient/book?service_id={$service->id}&amp;week_start={$weekStart}&amp;date={$date}#booking-step-day\"", false);
    $response->assertSee("href=\"http://127.0.0.1:8080/patient/book?service_id={$service->id}&amp;week_start={$weekStart}&amp;date={$date}&amp;slot_id={$slot->id}#booking-confirm\"", false);
  }

  public function test_patient_appointments_page_renders_compact_action_menu_and_cancel_panel(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);
    $pastSlot = $this->slot($doctor, $clinic, -48);
    $this->appointment($patient, $doctor, $service, $clinic, $pastSlot, Appointment::STATUS_COMPLETED);

    $response = $this->actingAs($patientUser)->get('/patient/appointments');

    $response->assertOk();
    $response->assertSee('aria-label="Azioni appuntamento"', false);
    $response->assertSee('data-bs-toggle="dropdown"', false);
    $response->assertSee('Sposta appuntamento');
    $response->assertSee("href=\"/appointments/{$appointment->id}/edit\"", false);
    $response->assertSee('Annulla appuntamento');
    $response->assertSee("data-cancel-panel-target=\"#appointmentCancelPanel{$appointment->id}\"", false);
    $response->assertSee("id=\"appointmentCancelPanel{$appointment->id}\"", false);
    $response->assertSee('Conferma annullamento');
    $response->assertSee("/appointments/{$appointment->id}/cancel", false);
    $response->assertSee('name="cancellation_reason"', false);
    $response->assertDontSee('Elimina appuntamento');
    $response->assertDontSee('Solo dettagli');
    $response->assertDontSee('data-bs-toggle="modal"', false);
    $response->assertDontSeeText('Confermato');
    $response->assertDontSeeText('Medico');
    $response->assertDontSeeText('Ambulatorio');
  }

  public function test_patient_appointments_empty_upcoming_state_links_to_booking(): void
  {
    [$patientUser] = $this->bookingContext();

    $response = $this->actingAs($patientUser)->get('/patient/appointments');

    $response->assertOk();
    $response->assertSee('Nessun appuntamento imminente');
    $response->assertSee('class="btn btn-primary empty-state__action" href="/patient/book"', false);
  }

  public function test_patient_can_open_reschedule_wizard_and_confirm_new_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $oldSlot] = $this->bookingContext();
    $newSlot = $this->slot($doctor, $clinic, 48);
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $oldSlot);
    $weekStart = $newSlot->start_at->toDateString();

    $response = $this->actingAs($patientUser)->get("/appointments/{$appointment->id}/edit?week_start={$weekStart}&date={$newSlot->start_at->toDateString()}&slot_id={$newSlot->id}");

    $response->assertOk();
    $response->assertSee('Stai riprogrammando');
    $response->assertSee($service->name);
    $response->assertSee($newSlot->start_at->format('H:i'));
    $response->assertSee('Conferma spostamento');
    $response->assertSee("/appointments/{$appointment->id}/reschedule", false);
  }

  public function test_patient_can_open_reschedule_wizard_even_when_no_other_slots_are_available(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $oldSlot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $oldSlot);

    $response = $this->actingAs($patientUser)->get("/appointments/{$appointment->id}/edit");

    $response->assertOk();
    $response->assertSee('Nessuna disponibilita per questa prestazione');
    $response->assertDontSee('Trying to access array offset on null');
  }

  public function test_patient_cannot_open_another_patients_reschedule_wizard(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $otherUser = $this->patient('other-patient')[0];
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);

    $this->actingAs($otherUser)->get("/appointments/{$appointment->id}/edit")->assertNotFound();
  }

  public function test_patient_cannot_cancel_non_confirmed_or_past_appointment(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot, Appointment::STATUS_COMPLETED);

    $response = $this->actingAs($patientUser)->from('/patient/appointments')->post("/appointments/{$appointment->id}/cancel");

    $response->assertRedirect('/patient/appointments');
    $response->assertSessionHasErrors('status');
    $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    $this->assertSame(0, AppointmentStatusHistory::count());
  }

  private function bookingContext(): array
  {
    [$patientUser, $patient] = $this->patient('patient');
    $doctorUser = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = \App\Models\DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
    ]);
    $service = MedicalService::create(['name' => 'Visita dermatologica']);
    $clinic = null;
    $slot = $this->slot($doctor, $clinic, 24);

    return [$patientUser, $patient, $doctor, $service, $clinic, $slot];
  }

  private function patient(string $username): array
  {
    $user = User::create([
      'username' => $username,
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $profile = PatientProfile::create(['user_id' => $user->id, 'phone' => '555-0100']);

    return [$user, $profile];
  }

  private function slot($doctor, $clinic, int $offsetHours): AvailabilitySlot
  {
    $start = CarbonImmutable::now()->addHours($offsetHours);

    return AvailabilitySlot::create([
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
    ]);
  }

  private function appointment(
    PatientProfile $patient,
    $doctor,
    MedicalService $service,
    $clinic,
    AvailabilitySlot $slot,
    string $status = Appointment::STATUS_CONFIRMED,
  ): Appointment {
    $slot->forceFill(['is_booked' => true])->save();

    return Appointment::create([
      'patient_id' => $patient->id,
      'service_id' => $service->id,
      'slot_id' => $slot->id,
      'start_at' => $slot->start_at,
      'end_at' => $slot->end_at,
      'status' => $status,
    ]);
  }
}
