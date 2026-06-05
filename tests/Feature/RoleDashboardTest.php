<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatie_role_middleware_allows_matching_portal_and_blocks_other_portal(): void
    {
        Role::findOrCreate(User::ROLE_PATIENT);
        Role::findOrCreate(User::ROLE_DOCTOR);

        $patientUser = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('patient123'),
        ]);
        $patientUser->assignRole(User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '3331234567']);

        $doctorUser = User::create([
            'email' => 'doctor@example.com',
            'password' => Hash::make('doctor123'),
        ]);
        $doctorUser->assignRole(User::ROLE_DOCTOR);
        DoctorProfile::create(['user_id' => $doctorUser->id, 'display_name' => 'Dott. Test']);

        $this->actingAs($patientUser)->get('/patient')->assertOk();
        $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
        $this->actingAs($doctorUser)->get('/doctor')->assertOk();
        $this->actingAs($doctorUser)->get('/patient')->assertForbidden();
    }

    public function test_doctor_dashboard_shows_appointment_summary_and_management_links(): void
    {
        [$doctorUser, , $appointment] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->get('/doctor')
            ->assertOk()
            ->assertSeeText('Portale medico')
            ->assertSeeText('Benvenuto Dott. Mbappe')
            ->assertSeeText('Prossimi appuntamenti')
            ->assertSeeText('2')
            ->assertSeeText('appuntamenti prenotati')
            ->assertSeeText('Prossimo appuntamento')
            ->assertSeeText('Visita dermatologica')
            ->assertSeeText($appointment->patientName())
            ->assertSeeText($appointment->start_at->format('d/m/Y H:i').' - '.$appointment->end_at->format('H:i'))
            ->assertSeeText('Gestisci Agenda')
            ->assertSeeText('Gestisci appuntamenti')
            ->assertSeeText('Gestisci Trattamenti')
            ->assertSee('href="/doctor/agenda"', false)
            ->assertSee('href="/doctor/appointments"', false)
            ->assertSee('href="/doctor/treatments"', false);
    }

    public function test_doctor_only_uses_agenda_section(): void
    {
        [$doctorUser, $doctor, $appointment] = $this->dashboardContext();

        $this->actingAs($doctorUser)->get('/doctor/schedule')->assertNotFound();
        $this->actingAs($doctorUser)->get('/doctor/availability')->assertNotFound();

        $this->actingAs($doctorUser)
            ->get('/doctor/agenda?date='.$appointment->start_at->toDateString())
            ->assertOk()
            ->assertSee('Agenda')
            ->assertDontSee('/doctor/agendav2', false)
            ->assertDontSee('href="/doctor/schedule"', false)
            ->assertDontSee('href="/doctor/availability"', false)
            ->assertSee('class="doctor-agenda-grid"', false)
            ->assertSee('Mario Rossi')
            ->assertSee('Altro Paziente')
            ->assertDontSee("/doctor/appointments/{$appointment->id}/status", false)
            ->assertDontSee('clinic_id')
            ->assertDontSee('slot_id');
    }

    public function test_doctor_agenda_timeline_shows_generated_slots_closures_and_appointments(): void
    {
        [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
        $date = CarbonImmutable::now()->addDays(3)->startOfDay();
        $patient = $appointment->patient;
        $service = $appointment->service;
        $this->slotAt($doctor, $date->setTime(10, 0));
        $closure = $doctor->closures()->create([
            'date' => $date->toDateString(),
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'reason' => 'Blocco',
        ]);
        $bookedSlot = $this->slotAt($doctor, $date->setTime(11, 0));
        $bookedAppointment = $this->appointment($patient, $doctor, $service, $bookedSlot);

        $this->actingAs($doctorUser)
            ->get("/doctor/agenda?date={$date->toDateString()}")
            ->assertOk()
            ->assertSee('class="doctor-agenda-grid"', false)
            ->assertSeeInOrder([
                '10:00',
                'Slot libero',
                '10:30',
                'Blocco',
                '11:00',
                'Mario Rossi',
                'Visita dermatologica',
            ])
            ->assertSee('action="/doctor/availability/block"', false)
            ->assertSee('name="slot_start"', false)
            ->assertSee('aria-label="Blocca slot libero"', false)
            ->assertSee('class="bi bi-slash-circle"', false)
            ->assertDontSee('<svg', false)
            ->assertDontSee('>Blocca</button>', false)
            ->assertSee("action=\"/doctor/closures/{$closure->id}\"", false)
            ->assertSee('aria-label="Riapri disponibilita"', false)
            ->assertSee('class="bi bi-unlock"', false)
            ->assertDontSee('>Riapri</button>', false)
            ->assertSee('aria-label="Informazioni appuntamento"', false)
            ->assertSee('class="bi bi-info-circle"', false)
            ->assertSee("data-bs-target=\"#appointmentInfoModal{$bookedAppointment->id}\"", false);
    }

    public function test_doctor_agenda_renders_cancel_button_and_patient_style_modal_for_appointments(): void
    {
        [$doctorUser, , $appointment] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->get('/doctor/agenda?date='.$appointment->start_at->toDateString())
            ->assertOk()
            ->assertSee('aria-label="Informazioni appuntamento"', false)
            ->assertSee('aria-label="Annulla appuntamento"', false)
            ->assertSee('title="Annulla appuntamento"', false)
            ->assertSee("data-bs-target=\"#appointmentCancelModal{$appointment->id}\"", false)
            ->assertSee("id=\"appointmentCancelModal{$appointment->id}\"", false)
            ->assertSee("action=\"/doctor/appointments/{$appointment->id}/cancel\"", false)
            ->assertSee('Lo slot verra liberato e tornera disponibile.')
            ->assertSee('Motivo opzionale');
    }

    public function test_doctor_agenda_modal_hides_empty_notes(): void
    {
        [$doctorUser, , $appointment] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->get('/doctor/agenda?date='.$appointment->start_at->toDateString())
            ->assertOk()
            ->assertDontSee('Note di prenotazione')
            ->assertDontSee('Nessuna nota');
    }

    public function test_doctor_can_cancel_own_future_active_appointment_from_agenda(): void
    {
        [$doctorUser, , $appointment] = $this->dashboardContext();
        $date = $appointment->start_at->toDateString();

        $this->actingAs($doctorUser)
            ->from('/doctor/agenda?date='.$date)
            ->post("/doctor/appointments/{$appointment->id}/cancel", [
                'cancellation_reason' => 'Cambio disponibilita',
            ])
            ->assertRedirect('/doctor/agenda?date='.$date)
            ->assertSessionHas('status', 'Appuntamento annullato.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
            'cancellation_reason' => 'Cambio disponibilita',
            'cancelled_by_role' => Appointment::CANCELLED_BY_DOCTOR,
            'cancelled_by_user_id' => $doctorUser->id,
        ]);
        $this->assertNotNull($appointment->fresh()->cancelled_at);
    }

    public function test_doctor_cannot_cancel_another_doctors_appointment(): void
    {
        [$doctorUser, , $appointment] = $this->dashboardContext();
        $otherDoctorUser = User::create([
            'email' => 'doctor.other@example.com',
            'password' => Hash::make('doctor123'),
        ]);
        $this->assignRole($otherDoctorUser, User::ROLE_DOCTOR);
        DoctorProfile::create([
            'user_id' => $otherDoctorUser->id,
            'display_name' => 'Dott. Altro',
        ]);

        $this->actingAs($otherDoctorUser)
            ->from('/doctor/agenda?date='.$appointment->start_at->toDateString())
            ->post("/doctor/appointments/{$appointment->id}/cancel", [
                'cancellation_reason' => 'Non autorizzato',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'cancellation_reason' => null,
        ]);
    }

    public function test_doctor_agenda_hides_cancelled_appointments_by_default(): void
    {
        [$doctorUser,, $cancelledAppointment] = $this->dashboardContext(Appointment::STATUS_CANCELLED);

        $this->actingAs($doctorUser)
            ->get('/doctor/agenda?date='.$cancelledAppointment->start_at->toDateString())
            ->assertOk()
            ->assertDontSee('Mario Rossi')
            ->assertDontSee("data-bs-target=\"#appointmentInfoModal{$cancelledAppointment->id}\"", false)
            ->assertSee('Altro Paziente');

        $this->actingAs($doctorUser)
            ->get('/doctor/agenda?date='.$cancelledAppointment->start_at->toDateString().'&status='.Appointment::STATUS_CANCELLED)
            ->assertOk()
            ->assertDontSee('Mario Rossi')
            ->assertDontSee("data-bs-target=\"#appointmentInfoModal{$cancelledAppointment->id}\"", false);
    }

    public function test_doctor_can_block_and_unblock_future_generated_slot_from_agenda(): void
    {
        [$doctorUser, $doctor] = $this->dashboardContext();
        $slot = $this->slotAt($doctor, CarbonImmutable::now()->addDays(3)->setTime(10, 0));

        $this->actingAs($doctorUser)
            ->from('/doctor/agenda')
            ->post('/doctor/availability/block', ['slot_start' => $slot->key])
            ->assertRedirect('/doctor/agenda');

        $closure = $doctor->closures()->firstOrFail();
        $this->assertSame($slot->start_at->toDateString(), $closure->date->toDateString());

        $this->actingAs($doctorUser)
            ->from('/doctor/agenda')
            ->delete("/doctor/closures/{$closure->id}")
            ->assertRedirect('/doctor/agenda?date='.$slot->start_at->toDateString());

        $this->assertSame(0, $doctor->closures()->count());
    }

    public function test_doctor_profiles_table_has_contact_and_clinic_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('doctor_profiles', 'phone'));
        $this->assertTrue(Schema::hasColumn('doctor_profiles', 'clinic_address'));
    }

    public function test_active_status_lives_on_users_not_doctor_profiles(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertFalse(Schema::hasColumn('doctor_profiles', 'is_active'));
        $this->assertFalse(Schema::hasColumn('doctor_profiles', 'bio'));
    }

    public function test_dynamic_availability_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('working_hours'));
        $this->assertTrue(Schema::hasTable('special_openings'));
        $this->assertTrue(Schema::hasTable('closures'));
        $this->assertTrue(Schema::hasColumn('medical_services', 'doctor_profile_id'));
        $this->assertFalse(Schema::hasColumn('appointments', 'doctor_profile_id'));
        $this->assertFalse(Schema::hasColumn('appointments', 'slot_id'));
    }

    public function test_doctor_can_manage_treatment_offerings(): void
    {
        [$doctorUser] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->post('/doctor/treatments', [
                'name' => 'Mappatura nei',
                'category' => 'ESAME',
                'price' => '95.50',
            ])
            ->assertRedirect('/doctor/treatments');

        $offering = MedicalService::where('name', 'Mappatura nei')->firstOrFail();

        $this->actingAs($doctorUser)
            ->patch("/doctor/treatments/{$offering->id}", [
                'name' => 'Dermatoscopia',
                'category' => 'ESAME',
                'price' => '110.00',
            ])
            ->assertRedirect('/doctor/treatments');

        $this->assertDatabaseHas('medical_services', [
            'id' => $offering->id,
            'doctor_profile_id' => $doctorUser->doctorProfile->id,
            'name' => 'Dermatoscopia',
            'category' => 'ESAME',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($doctorUser)
            ->delete("/doctor/treatments/{$offering->id}")
            ->assertRedirect('/doctor/treatments');

        $this->assertDatabaseHas('medical_services', ['id' => $offering->id, 'is_active' => false]);
    }

    public function test_doctor_can_view_and_update_profile_contact_fields(): void
    {
        [$doctorUser, $doctor] = $this->dashboardContext();
        $doctorUser->forceFill(['email' => 'doctor.old@example.com'])->save();
        $doctor->forceFill([
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1',
        ])->save();

        $this->actingAs($doctorUser)
            ->get('/doctor/profile')
            ->assertOk()
            ->assertSee('Profilo medico')
            ->assertSee('doctor.old@example.com')
            ->assertSee('3331000000')
            ->assertSee('Via Roma 1')
            ->assertSee('Agenda')
            ->assertDontSee('name="email"', false)
            ->assertDontSee('href="/doctor/availability"', false);

        $this->actingAs($doctorUser)
            ->patch('/doctor/profile', [
                'email' => 'doctor.new@example.com',
                'phone' => '3332000000',
                'clinic_address' => 'Via Milano 2',
            ])
            ->assertRedirect('/doctor/profile');

        $this->assertSame('doctor.old@example.com', $doctorUser->fresh()->email);
        $this->assertSame('3332000000', $doctor->fresh()->phone);
        $this->assertSame('Via Milano 2', $doctor->fresh()->clinic_address);
    }

    public function test_doctor_profile_rejects_invalid_phone_number(): void
    {
        [$doctorUser, $doctor] = $this->dashboardContext();
        $doctor->forceFill([
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1',
        ])->save();

        $this->actingAs($doctorUser)
            ->from('/doctor/profile')
            ->patch('/doctor/profile', [
                'email' => 'doctor.new@example.com',
                'phone' => 'telefono',
                'clinic_address' => 'Via Milano 2',
            ])
            ->assertRedirect('/doctor/profile')
            ->assertSessionHasErrors('phone');

        $this->assertSame('3331000000', $doctor->fresh()->phone);
    }

    public function test_doctor_profile_allows_empty_phone_number(): void
    {
        [$doctorUser, $doctor] = $this->dashboardContext();
        $doctor->forceFill([
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1',
        ])->save();

        $this->actingAs($doctorUser)
            ->patch('/doctor/profile', [
                'email' => 'doctor.new@example.com',
                'phone' => '',
                'clinic_address' => 'Via Milano 2',
            ])
            ->assertRedirect('/doctor/profile');

        $this->assertSame('', $doctor->fresh()->phone);
    }

    public function test_doctor_profile_page_shows_password_change_form(): void
    {
        [$doctorUser] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->get('/doctor/profile')
            ->assertOk()
            ->assertSee('Password')
            ->assertSee('action="/doctor/password"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Aggiorna password');
    }

    public function test_doctor_can_change_password_from_profile(): void
    {
        [$doctorUser] = $this->dashboardContext();
        $doctorUser->forceFill(['password' => Hash::make('old-password')])->save();

        $this->actingAs($doctorUser)
            ->put('/doctor/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/doctor/profile')
            ->assertSessionHas('status', 'Password aggiornata.');

        $this->assertTrue(Hash::check('new-password', $doctorUser->fresh()->password));
    }

    public function test_doctor_password_change_rejects_wrong_current_password(): void
    {
        [$doctorUser] = $this->dashboardContext();
        $doctorUser->forceFill(['password' => Hash::make('old-password')])->save();

        $this->actingAs($doctorUser)
            ->from('/doctor/profile')
            ->put('/doctor/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/doctor/profile')
            ->assertSessionHasErrors([
                'current_password' => 'La password attuale non e corretta.',
            ]);

        $this->assertTrue(Hash::check('old-password', $doctorUser->fresh()->password));
    }

    public function test_patient_cannot_access_doctor_routes(): void
    {
        [$patientUser] = $this->patient('patient@example.com');

        $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
        $this->actingAs($patientUser)->get('/doctor/profile')->assertForbidden();
    }

    public function test_patient_dashboard_shows_next_appointment_and_appointments_link(): void
    {
        [, , $appointment] = $this->dashboardContext();

        $response = $this->actingAs($appointment->patient->user)
            ->get('/patient')
            ->assertOk()
            ->assertSeeText('Benvenuto Mario')
            ->assertDontSeeText('Benvenuto nella clinica di Dott. Mbappe')
            ->assertSeeText('Il tuo medico')
            ->assertSee('class="doctor-summary__contact doctor-summary__contact--email"', false)
            ->assertSeeText('doctor.derm@example.com')
            ->assertSeeText('3331000000')
            ->assertSeeText('Via Roma 1')
            ->assertDontSeeText('Dermatologo specializzato in prevenzione.')
            ->assertSeeText('Prossimo appuntamento')
            ->assertSeeText('Visita dermatologica')
            ->assertSeeText('I miei appuntamenti')
            ->assertSee('href="/patient/appointments"', false)
            ->assertDontSee('Appuntamenti imminenti')
            ->assertDontSee('patient-upcoming-panel', false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'class="dashboard-doctor-section'),
            strpos($content, 'href="/patient/appointments"'),
        );
        $this->assertLessThan(strpos($content, '<dt>Email</dt>'), strpos($content, '<dt>Studio</dt>'));
        $this->assertLessThan(strpos($content, '<dt>Telefono</dt>'), strpos($content, '<dt>Email</dt>'));
    }

    public function test_legacy_doctor_agendav2_url_is_not_registered(): void
    {
        [$doctorUser] = $this->dashboardContext();

        $this->actingAs($doctorUser)
            ->get('/doctor/agendav2?date=2030-04-29')
            ->assertNotFound();
    }

    private function dashboardContext(string $status = Appointment::STATUS_CONFIRMED): array
    {
        $doctorUser = User::create([
            'email' => 'doctor.derm@example.com',
            'password' => Hash::make('doctor123'),
        ]);
        $this->assignRole($doctorUser, User::ROLE_DOCTOR);
        $doctor = DoctorProfile::create([
            'user_id' => $doctorUser->id,
            'display_name' => 'Dott. Mbappe',
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1',
        ]);
        [$patientUser, $patient] = $this->patient('patient@example.com', 'Mario', 'Rossi');
        [$otherPatientUser, $otherPatient] = $this->patient('other@example.com', 'Altro', 'Paziente');
        $service = MedicalService::create([
            'doctor_profile_id' => $doctor->id,
            'name' => 'Visita dermatologica',
        ]);
        $slot = $this->slotAt($doctor, CarbonImmutable::now()->addDay()->setTime(9, 0));
        $otherSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDay()->setTime(10, 0));
        $appointment = $this->appointment($patient, $doctor, $service, $slot, $status);
        $this->appointment($otherPatient, $doctor, $service, $otherSlot);

        return [$doctorUser, $doctor, $appointment];
    }

    private function patient(string $email, string $firstName = '', string $lastName = ''): array
    {
        $user = User::create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        $profile = PatientProfile::create(['user_id' => $user->id, 'phone' => '3331234567']);

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
    ): Appointment {
        return Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'status' => $status,
        ]);
    }
}
