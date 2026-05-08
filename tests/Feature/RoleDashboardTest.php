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
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
  use RefreshDatabase;

  public function test_doctor_only_uses_agenda_section(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();

    $this->actingAs($doctorUser)->get('/doctor')->assertRedirect('/doctor/agenda');
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
        'Blocca',
        '10:30',
        'Blocco',
        'Riapri',
        '11:00',
        'Mario Rossi',
        'Visita dermatologica',
      ])
      ->assertSee('action="/doctor/availability/block"', false)
      ->assertSee('name="slot_start"', false)
      ->assertSee("action=\"/doctor/closures/{$closure->id}\"", false)
      ->assertSee('aria-label="Informazioni appuntamento"', false)
      ->assertSee("data-bs-target=\"#appointmentInfoModal{$bookedAppointment->id}\"", false);
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

  public function test_dynamic_availability_tables_exist(): void
  {
    $this->assertTrue(Schema::hasTable('working_hours'));
    $this->assertTrue(Schema::hasTable('special_openings'));
    $this->assertTrue(Schema::hasTable('closures'));
    $this->assertTrue(Schema::hasColumn('appointments', 'doctor_profile_id'));
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
      'phone' => '555-1000',
      'clinic_address' => 'Via Roma 1',
    ])->save();

    $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('Profilo medico')
      ->assertSee('doctor.old@example.com')
      ->assertSee('555-1000')
      ->assertSee('Via Roma 1')
      ->assertSee('Agenda')
      ->assertDontSee('href="/doctor/availability"', false);

    $this->actingAs($doctorUser)
      ->patch('/doctor/profile', [
        'email' => 'doctor.new@example.com',
        'phone' => '555-2000',
        'clinic_address' => 'Via Milano 2',
      ])
      ->assertRedirect('/doctor/profile');

    $this->assertSame('doctor.new@example.com', $doctorUser->fresh()->email);
    $this->assertSame('555-2000', $doctor->fresh()->phone);
    $this->assertSame('Via Milano 2', $doctor->fresh()->clinic_address);
  }

  public function test_patient_cannot_access_doctor_routes(): void
  {
    [$patientUser] = $this->patient('patient');

    $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
    $this->actingAs($patientUser)->get('/doctor/profile')->assertForbidden();
  }

  public function test_patient_dashboard_links_all_appointments_from_next_appointment_panel(): void
  {
    [, , $appointment] = $this->dashboardContext();

    $response = $this->actingAs($appointment->patient->user)
      ->get('/patient')
      ->assertOk();

    $content = $response->getContent();

    $this->assertMatchesRegularExpression(
      '/<div class="section-heading">\s*<h2>Prossimo appuntamento<\/h2>\s*<a href="\/patient\/appointments">Vedi tutti<\/a>\s*<\/div>/',
      $content,
    );
    $this->assertStringNotContainsString('Appuntamenti imminenti', $content);
    $this->assertStringNotContainsString('patient-upcoming-panel', $content);
  }

  public function test_legacy_doctor_agendav2_url_redirects_to_agenda(): void
  {
    [$doctorUser] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor/agendav2?date=2030-04-29')
      ->assertRedirect('/doctor/agenda?date=2030-04-29');
  }

  private function dashboardContext(string $status = Appointment::STATUS_CONFIRMED): array
  {
    $doctorUser = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
    ]);
    [$patientUser, $patient] = $this->patient('patient', 'Mario', 'Rossi');
    [$otherPatientUser, $otherPatient] = $this->patient('other', 'Altro', 'Paziente');
    $service = MedicalService::create(['name' => 'Visita dermatologica']);
    $slot = $this->slotAt($doctor, CarbonImmutable::now()->addDay()->setTime(9, 0));
    $otherSlot = $this->slotAt($doctor, CarbonImmutable::now()->addDay()->setTime(10, 0));
    $appointment = $this->appointment($patient, $doctor, $service, $slot, $status);
    $this->appointment($otherPatient, $doctor, $service, $otherSlot);

    return [$doctorUser, $doctor, $appointment];
  }

  private function patient(string $username, string $firstName = '', string $lastName = ''): array
  {
    $user = User::create([
      'username' => $username,
      'first_name' => $firstName,
      'last_name' => $lastName,
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
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
  ): Appointment {
    return Appointment::create([
      'patient_id' => $patient->id,
      'doctor_profile_id' => $doctor->id,
      'service_id' => $service->id,
      'start_at' => $slot->start_at,
      'end_at' => $slot->end_at,
      'status' => $status,
    ]);
  }
}
