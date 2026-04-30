<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
  use RefreshDatabase;

  public function test_doctor_sees_schedule_without_raw_data_or_action_buttons(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $appointmentDate = $appointment->start_at->toDateString();

    $this->actingAs($doctorUser)
      ->get("/doctor/schedule?date={$appointmentDate}")
      ->assertOk()
      ->assertSee('Agenda del giorno')
      ->assertSee('class="doctor-agenda-timeline"', false)
      ->assertSee('doctor-agenda-item--appointment', false)
      ->assertSee('Mario Rossi')
      ->assertDontSee('Altro Paziente')
      ->assertSee('Gestisci disponibilita')
      ->assertSee('<details class="availability-manager"', false)
      ->assertSee('<details class="availability-day"', false)
      ->assertSee('/doctor/availability/', false)
      ->assertSee('type="hidden" name="slot_duration" value="30"', false)
      ->assertDontSee('Durata slot')
      ->assertDontSee('<select class="form-select" name="slot_duration"', false)
      ->assertDontSee('Agenda completa')
      ->assertDontSee('<table class="table dashboard-table', false)
      ->assertDontSee('Azioni')
      ->assertDontSee('Accetta')
      ->assertDontSee('Assente')
      ->assertDontSee('Conferma assenza')
      ->assertDontSee("/doctor/appointments/{$appointment->id}/status", false)
      ->assertDontSee('doctor_id')
      ->assertDontSee('clinic_id')
      ->assertDontSee('start_at');

    $this->actingAs($doctorUser)
      ->post("/doctor/appointments/{$appointment->id}/status", ['status' => Appointment::STATUS_CHECKED_IN])
      ->assertRedirect('/doctor/schedule');

    $this->assertSame(Appointment::STATUS_CHECKED_IN, $appointment->fresh()->status);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'changed_by' => $doctorUser->id,
      'new_status' => Appointment::STATUS_CHECKED_IN,
    ]);
  }

  public function test_doctor_schedule_without_date_defaults_to_today(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $today = now()->toDateString();

    $this->actingAs($doctorUser)
      ->get('/doctor/schedule')
      ->assertOk()
      ->assertSee("value=\"{$today}\"", false)
      ->assertSee("{$today}")
      ->assertSee('Agenda del giorno');
  }

  public function test_doctor_agenda_timeline_shows_selected_day_slots_with_inline_actions(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $date = CarbonImmutable::now()->addDays(3)->toDateString();
    $patient = $appointment->patient;
    $service = $appointment->service;

    $freeSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 10:00:00"),
      'end_at' => CarbonImmutable::parse("{$date} 10:30:00"),
    ]);
    $blockedSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 10:30:00"),
      'end_at' => CarbonImmutable::parse("{$date} 11:00:00"),
      'is_blocked' => true,
    ]);
    $bookedSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 11:00:00"),
      'end_at' => CarbonImmutable::parse("{$date} 11:30:00"),
      'is_booked' => true,
    ]);
    $this->appointment($patient, $service, $bookedSlot);

    $this->actingAs($doctorUser)
      ->get("/doctor/schedule?date={$date}")
      ->assertOk()
      ->assertSee('class="doctor-agenda-timeline"', false)
      ->assertSeeInOrder([
        '10:00',
        'Libero',
        'Blocca',
        '10:30',
        'Bloccato',
        'Riapri',
        '11:00',
        'Mario Rossi',
        'Visita dermatologica',
      ])
      ->assertSee("action=\"/doctor/availability/{$freeSlot->id}/block\"", false)
      ->assertSee("action=\"/doctor/availability/{$blockedSlot->id}/unblock\"", false)
      ->assertDontSee("action=\"/doctor/availability/{$bookedSlot->id}/block\"", false)
      ->assertDontSee("action=\"/doctor/availability/{$bookedSlot->id}/unblock\"", false);
  }

  public function test_doctor_today_uses_timeline_without_availability_management(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $todayStart = CarbonImmutable::now()->setTime(9, 0);
    $todaySlot = AvailabilitySlot::create([
      'start_at' => $todayStart,
      'end_at' => $todayStart->addMinutes(30),
      'is_booked' => true,
    ]);
    $this->appointment($appointment->patient, $appointment->service, $todaySlot);

    $this->actingAs($doctorUser)
      ->get('/doctor')
      ->assertOk()
      ->assertSee('class="doctor-agenda-timeline"', false)
      ->assertSee('doctor-agenda-item--appointment', false)
      ->assertSee('Mario Rossi')
      ->assertDontSee('Gestisci disponibilita')
      ->assertDontSee('class="availability-manager"', false)
      ->assertDontSee('/doctor/availability/preview', false);
  }

  public function test_doctor_can_block_and_unblock_future_availability_slot(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $slot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 0),
      'end_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 30),
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post("/doctor/availability/{$slot->id}/block")
      ->assertRedirect('/doctor/schedule');

    $this->assertTrue($slot->fresh()->is_blocked);

    $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post("/doctor/availability/{$slot->id}/unblock")
      ->assertRedirect('/doctor/schedule');

    $this->assertFalse($slot->fresh()->is_blocked);
  }

  public function test_doctor_can_preview_batch_availability_with_creatable_and_skipped_counts(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    AvailabilitySlot::create([
      'start_at' => $startDate->setTime(9, 0),
      'end_at' => $startDate->setTime(9, 30),
    ]);

    $response = $this->actingAs($doctorUser)->get('/doctor/availability/preview?'.http_build_query([
      'start_date' => $startDate->toDateString(),
      'end_date' => $startDate->toDateString(),
      'weekdays' => [$startDate->dayOfWeekIso],
      'start_time' => '09:00',
      'end_time' => '10:00',
      'slot_duration' => 30,
    ]));

    $response->assertOk();
    $response->assertSee('Anteprima disponibilita');
    $response->assertSee('1 slot creabile');
    $response->assertSee('1 saltato');
    $response->assertDontSee('Ambulatorio');
    $response->assertDontSee('durata slot');
    $response->assertSee('09:30');
  }

  public function test_doctor_can_create_batch_availability_without_duplicates(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::TUESDAY);
    AvailabilitySlot::create([
      'start_at' => $startDate->setTime(14, 0),
      'end_at' => $startDate->setTime(14, 30),
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/availability/batch', [
        'start_date' => $startDate->toDateString(),
        'end_date' => $startDate->toDateString(),
        'weekdays' => [$startDate->dayOfWeekIso],
        'start_time' => '14:00',
        'end_time' => '15:00',
        'slot_duration' => 30,
      ])
      ->assertRedirect('/doctor/schedule');

    $this->assertDatabaseHas('availability_slots', [
      'start_at' => $startDate->setTime(14, 30)->toDateTimeString(),
    ]);
    $this->assertSame(
      2,
      AvailabilitySlot::whereDate('start_at', $startDate->toDateString())
        ->whereTime('start_at', '>=', '14:00')
        ->whereTime('start_at', '<', '15:00')
        ->count(),
    );
  }

  public function test_doctor_cannot_create_batch_availability_in_the_past(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $pastDate = CarbonImmutable::now()->subWeeks(2)->startOfWeek();

    $response = $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post('/doctor/availability/batch', [
        'start_date' => $pastDate->toDateString(),
        'end_date' => $pastDate->toDateString(),
        'weekdays' => [$pastDate->dayOfWeekIso],
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_duration' => 30,
      ]);

    $response->assertRedirect('/doctor/schedule');
    $response->assertSessionHasErrors('availability');
  }

  public function test_doctor_can_manage_treatment_offerings(): void
  {
    [$doctorUser] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor/treatments')
      ->assertOk()
      ->assertSee('Trattamenti');

    $this->actingAs($doctorUser)
      ->post('/doctor/treatments', [
        'name' => 'Mappatura nei',
        'category' => MedicalService::CATEGORY_EXAM,
        'price' => '95.50',
      ])
      ->assertRedirect('/doctor/treatments');

    $offering = MedicalService::where('name', 'Mappatura nei')->firstOrFail();

    $this->actingAs($doctorUser)
      ->get("/doctor/treatments/{$offering->id}/edit")
      ->assertOk()
      ->assertSee('Modifica trattamento')
      ->assertDontSee('name="specialty_id"', false)
      ->assertDontSee('name="duration_minutes"', false)
      ->assertDontSee('Trattamento attivo');

    $this->actingAs($doctorUser)
      ->patch("/doctor/treatments/{$offering->id}", [
        'name' => 'Dermatoscopia',
        'category' => MedicalService::CATEGORY_EXAM,
        'price' => '110.00',
      ])
      ->assertRedirect('/doctor/treatments');

    $this->assertDatabaseHas('medical_services', [
      'id' => $offering->id,
      'name' => 'Dermatoscopia',
      'duration_minutes' => 30,
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->get('/doctor/treatments')
      ->assertOk()
      ->assertSee('aria-label="Azioni trattamento"', false)
      ->assertSee('data-bs-toggle="dropdown"', false)
      ->assertSee('Modifica trattamento')
      ->assertSee("href=\"/doctor/treatments/{$offering->id}/edit\"", false)
      ->assertSee('Elimina trattamento')
      ->assertSee("action=\"/doctor/treatments/{$offering->id}\"", false)
      ->assertDontSee('class="treatment-card__actions"', false)
      ->assertDontSee('Attivo')
      ->assertDontSee('Disattiva')
      ->assertDontSee('Durata');

    $this->actingAs($doctorUser)
      ->delete("/doctor/treatments/{$offering->id}")
      ->assertRedirect('/doctor/treatments');

    $this->assertDatabaseMissing('medical_services', [
      'id' => $offering->id,
    ]);
  }

  public function test_patient_cannot_access_doctor_routes(): void
  {
    $patientUser = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);

    $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
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
    $patientUser = User::create([
      'username' => 'patient',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $patient = PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);
    $otherPatientUser = User::create([
      'username' => 'other',
      'first_name' => 'Altro',
      'last_name' => 'Paziente',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $otherPatient = PatientProfile::create(['user_id' => $otherPatientUser->id, 'phone' => '555-0101']);
    $service = MedicalService::create(['name' => 'Visita dermatologica']);
    $slot = $this->slot(24);
    $otherSlot = $this->slot(25);
    $appointment = $this->appointment($patient, $service, $slot, $status);
    $this->appointment($otherPatient, $service, $otherSlot);

    return [$doctorUser, $doctor, $appointment];
  }

  private function slot(int $offsetHours): AvailabilitySlot
  {
    $start = CarbonImmutable::now()->addHours($offsetHours);

    return AvailabilitySlot::create([
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
      'is_booked' => true,
    ]);
  }

  private function appointment(
    PatientProfile $patient,
    MedicalService $service,
    AvailabilitySlot $slot,
    string $status = Appointment::STATUS_CONFIRMED,
  ): Appointment {
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
