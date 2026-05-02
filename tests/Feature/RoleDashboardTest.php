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
use Illuminate\Support\Facades\Schema;
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
      ->assertSee('class="doctor-agenda-grid"', false)
      ->assertSee('doctor-agenda-item--appointment', false)
      ->assertSee('Mario Rossi')
      ->assertSee('Altro Paziente')
      ->assertDontSeeText('Confermato')
      ->assertDontSee('Gestisci disponibilita')
      ->assertDontSee('<details class="availability-manager"', false)
      ->assertDontSee('<details class="availability-day"', false)
      ->assertDontSee('type="hidden" name="slot_duration" value="30"', false)
      ->assertDontSee('Durata slot')
      ->assertDontSee('<select class="form-select" name="slot_duration"', false)
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
    $previousDay = CarbonImmutable::parse($today)->subDay()->toDateString();
    $nextDay = CarbonImmutable::parse($today)->addDay()->toDateString();

    $this->actingAs($doctorUser)
      ->get('/doctor/schedule')
      ->assertOk()
      ->assertSee('aria-label="Giorno precedente"', false)
      ->assertSee('aria-label="Giorno successivo"', false)
      ->assertSee("href=\"http://127.0.0.1:8080/doctor/schedule?date={$previousDay}\"", false)
      ->assertSee("href=\"http://127.0.0.1:8080/doctor/schedule?date={$nextDay}\"", false)
      ->assertSee("value=\"{$today}\"", false)
      ->assertSee("{$today}")
      ->assertSee('Agenda del giorno')
      ->assertSee('dashboard-stat dashboard-stat--day-nav', false)
      ->assertDontSee('<span>Bloccati</span>', false);
  }

  public function test_doctor_schedule_day_navigation_preserves_filters(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $date = CarbonImmutable::parse('2026-06-02');
    $previousDay = $date->subDay()->toDateString();
    $nextDay = $date->addDay()->toDateString();

    $this->actingAs($doctorUser)
      ->get("/doctor/schedule?date={$date->toDateString()}&status=".Appointment::STATUS_CONFIRMED)
      ->assertOk()
      ->assertSee('<p>Martedì 02/06/2026</p>', false)
      ->assertDontSee("<small>{$date->toDateString()}</small>", false)
      ->assertDontSee('<small>0 prenotati</small>', false)
      ->assertSee("value=\"{$date->toDateString()}\"", false)
      ->assertSee("href=\"http://127.0.0.1:8080/doctor/schedule?date={$previousDay}&amp;status=".Appointment::STATUS_CONFIRMED."\"", false)
      ->assertSee("href=\"http://127.0.0.1:8080/doctor/schedule?date={$nextDay}&amp;status=".Appointment::STATUS_CONFIRMED."\"", false);
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
      ->assertSee('class="doctor-agenda-grid"', false)
      ->assertSeeInOrder([
        '10:00',
        'Slot libero',
        'Blocca',
        '10:30',
        'Slot bloccato',
        'Riapri',
        '11:00',
        'Mario Rossi',
        'Visita dermatologica',
      ])
      ->assertSee("action=\"/doctor/availability/{$freeSlot->id}/block\"", false)
      ->assertSee("action=\"/doctor/availability/{$blockedSlot->id}/unblock\"", false)
      ->assertDontSee('<p>10:00 - 10:30</p>', false)
      ->assertDontSee('<p>10:30 - 11:00</p>', false)
      ->assertDontSee('<span>11:00 - 11:30</span>', false)
      ->assertDontSee('<span class="badge text-bg-success">Libero</span>', false)
      ->assertDontSee('<span class="badge text-bg-warning">Bloccato</span>', false)
      ->assertDontSee("action=\"/doctor/availability/{$bookedSlot->id}/block\"", false)
      ->assertDontSee("action=\"/doctor/availability/{$bookedSlot->id}/unblock\"", false);
  }

  public function test_doctor_agenda_renders_full_day_half_hour_grid_with_empty_rows(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $date = CarbonImmutable::now()->addDays(4)->toDateString();
    $patient = $appointment->patient;
    $service = $appointment->service;

    $freeSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 10:00:00"),
      'end_at' => CarbonImmutable::parse("{$date} 10:30:00"),
    ]);
    $bookedSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 11:00:00"),
      'end_at' => CarbonImmutable::parse("{$date} 11:30:00"),
      'is_booked' => true,
    ]);
    $this->appointment($patient, $service, $bookedSlot);

    $response = $this->actingAs($doctorUser)->get("/doctor/schedule?date={$date}");
    $content = $response->getContent();

    $response
      ->assertOk()
      ->assertSee('class="doctor-agenda-grid"', false)
      ->assertSee('data-time="00:00"', false)
      ->assertSee('data-time="00:30"', false)
      ->assertSee('data-time="23:30"', false)
      ->assertSee('data-agenda-occupied-row', false)
      ->assertSee('doctor-agenda-row__content" aria-hidden="true"', false)
      ->assertSee('Slot libero')
      ->assertSee('Blocca')
      ->assertSee('Mario Rossi')
      ->assertSee('Visita dermatologica');

    $this->assertSame(48, substr_count($content, 'data-time="'));
    $this->assertStringContainsString("action=\"/doctor/availability/{$freeSlot->id}/block\"", $content);
  }

  public function test_today_agenda_marks_current_time_and_dims_past_rows_without_passato_badge(): void
  {
    $now = CarbonImmutable::parse('2026-05-01 10:15:00');
    \Carbon\Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
      [$doctorUser] = $this->dashboardContext();
      $date = $now->toDateString();

      AvailabilitySlot::create([
        'start_at' => $now->setTime(9, 0),
        'end_at' => $now->setTime(9, 30),
      ]);
      AvailabilitySlot::create([
        'start_at' => $now->setTime(11, 0),
        'end_at' => $now->setTime(11, 30),
      ]);

      $this->actingAs($doctorUser)
        ->get("/doctor/schedule?date={$date}")
        ->assertOk()
        ->assertSee('data-agenda-scroll-container', false)
        ->assertSee('class="doctor-agenda-now-marker"', false)
        ->assertSee('data-agenda-now-marker', false)
        ->assertSee('Ora 10:15')
        ->assertSee('doctor-agenda-row doctor-agenda-row--past', false)
        ->assertSee('Slot libero')
        ->assertSee('Blocca')
        ->assertDontSee('Passato');
    } finally {
      \Carbon\Carbon::setTestNow();
      CarbonImmutable::setTestNow();
    }
  }

  public function test_past_date_agenda_keeps_passato_badge_without_current_time_marker(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $date = CarbonImmutable::now()->subDays(2)->toDateString();

    AvailabilitySlot::create([
      'start_at' => CarbonImmutable::parse("{$date} 09:00:00"),
      'end_at' => CarbonImmutable::parse("{$date} 09:30:00"),
    ]);

    $this->actingAs($doctorUser)
      ->get("/doctor/schedule?date={$date}")
      ->assertOk()
      ->assertSee('Passato')
      ->assertDontSee('doctor-agenda-now-marker', false);
  }

  public function test_doctor_root_redirects_to_schedule_without_today_section(): void
  {
    [$doctorUser] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor')
      ->assertRedirect('/doctor/schedule');

    $this->actingAs($doctorUser)
      ->get('/doctor/schedule')
      ->assertOk()
      ->assertDontSee('Oggi')
      ->assertSee('Agenda')
      ->assertSee('Agenda del giorno')
      ->assertDontSee('Gestisci disponibilita')
      ->assertDontSee('/doctor/availability/preview', false);
  }

  public function test_doctor_availability_is_a_dedicated_section(): void
  {
    [$doctorUser] = $this->dashboardContext();
    $freeSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 0),
      'end_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 30),
    ]);
    $blockedSlot = AvailabilitySlot::create([
      'start_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 30),
      'end_at' => CarbonImmutable::now()->addDays(3)->setTime(11, 0),
      'is_blocked' => true,
    ]);

    $this->actingAs($doctorUser)
      ->get('/doctor/availability')
      ->assertOk()
      ->assertSee('Disponibilita')
      ->assertSee('Gestisci disponibilita')
      ->assertSee('Crea disponibilita')
      ->assertSee('Slot totali')
      ->assertSee('Liberi')
      ->assertSee('Prenotati')
      ->assertSee('Pausa pranzo')
      ->assertSee('class="avail-tabs-wrap"', false)
      ->assertSee('avail-day-tab', false)
      ->assertSee('week-day__name', false)
      ->assertSee('week-day__month', false)
      ->assertDontSee('previousElementSibling.click()', false)
      ->assertSee('class="avail-slot-panel"', false)
      ->assertSee('class="avail-slot-row"', false)
      ->assertDontSee('Disponibilita future')
      ->assertDontSee('class="availability-manager"', false)
      ->assertDontSee('class="availability-day"', false)
      ->assertSee('/doctor/availability/preview', false)
      ->assertSee('type="hidden" name="slot_duration" value="30"', false)
      ->assertSee('availability-lunch-card availability-lunch-card--collapsed', false)
      ->assertSee('data-availability-lunch-fields', false)
      ->assertSee("action=\"/doctor/availability/{$freeSlot->id}/block\"", false)
      ->assertSee("action=\"/doctor/availability/{$blockedSlot->id}/unblock\"", false)
      ->assertDontSee('<span class="badge text-bg-success">Libero</span>', false)
      ->assertDontSee('<span class="badge text-bg-warning">Bloccato</span>', false)
      ->assertSee('Agenda')
      ->assertSee('Trattamenti')
      ->assertDontSee('Agenda del giorno');
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

  public function test_doctor_can_preview_batch_availability_without_showing_skipped_slots(): void
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
    $response->assertSee('Disponibilita');
    $response->assertDontSee('Agenda del giorno');
    $response->assertSee('Anteprima slot');
    $response->assertSee('data-availability-preview-open', false);
    $response->assertSee('data-availability-preview-step', false);
    $response->assertSee('class="availability-step availability-step--form d-none" data-availability-form-step', false);
    $response->assertSee('availability-preview-card', false);
    $response->assertSee('form="availability-create-form"', false);
    $response->assertSee('Crea 1 slot');
    $response->assertSee('Modifica');
    $response->assertDontSee('Slot saltato');
    $response->assertDontSee('slot saltato');
    $response->assertDontSee('dashboard-filter-panel', false);
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
      ->assertRedirect('/doctor/availability');

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
      ->from('/doctor/availability')
      ->post('/doctor/availability/batch', [
        'start_date' => $pastDate->toDateString(),
        'end_date' => $pastDate->toDateString(),
        'weekdays' => [$pastDate->dayOfWeekIso],
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_duration' => 30,
      ]);

    $response->assertRedirect('/doctor/availability');
    $response->assertSessionHasErrors('availability');
  }

  public function test_doctor_can_manage_treatment_offerings(): void
  {
    [$doctorUser] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor/treatments')
      ->assertOk()
      ->assertSee('Trattamenti')
      ->assertDontSee("Torna all'agenda", false);

    $this->actingAs($doctorUser)
      ->post('/doctor/treatments', [
        'name' => 'Mappatura nei',
        'category' => 'ESAME',
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
      ->assertDontSee('Durata')
      ->assertSee("data-bs-target=\"#deleteTreatmentModal{$offering->id}\"", false)
      ->assertSee("id=\"deleteTreatmentModal{$offering->id}\"", false)
      ->assertSee('Conferma eliminazione', false)
      ->assertSee('Gli appuntamenti già prenotati resteranno validi.', false)
      ->assertDontSee('window.confirm', false);

    $this->actingAs($doctorUser)
      ->delete("/doctor/treatments/{$offering->id}")
      ->assertRedirect('/doctor/treatments');

    $this->assertDatabaseHas('medical_services', ['id' => $offering->id, 'is_active' => false]);

    $this->actingAs($doctorUser)
      ->get('/doctor/treatments')
      ->assertOk()
      ->assertDontSee($offering->name);
  }

public function test_doctor_profiles_table_has_contact_and_clinic_fields(): void
  {
    $this->assertTrue(Schema::hasColumn('doctor_profiles', 'phone'));
    $this->assertTrue(Schema::hasColumn('doctor_profiles', 'clinic_address'));
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
      ->assertSee("Numero di iscrizione all'albo", false)
      ->assertDontSee('Numero iscrizione')
      ->assertSee('doctor.old@example.com')
      ->assertSee('555-1000')
      ->assertSee('Via Roma 1')
      ->assertSee('Agenda')
      ->assertSee('Disponibilita')
      ->assertSee('Trattamenti');

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
    $patientUser = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);

    $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
    $this->actingAs($patientUser)->get('/doctor/profile')->assertForbidden();
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

  private function makeDoctorUser(): User
  {
    $doctorUser = User::create([
      'username' => 'doctor.test',
      'password' => \Illuminate\Support\Facades\Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Test',
    ]);

    return $doctorUser;
  }

  public function test_doctor_preview_excludes_lunch_break_slots(): void
  {
    $doctorUser = $this->makeDoctorUser();
    $targetDate = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $startDate  = $targetDate->toDateString();

    $response = $this->actingAs($doctorUser)->get('/doctor/availability/preview?' . http_build_query([
      'start_date'          => $startDate,
      'end_date'            => $startDate,
      'weekdays'            => [$targetDate->dayOfWeekIso],
      'start_time'          => '09:00',
      'end_time'            => '14:00',
      'slot_duration'       => '30',
      'lunch_break_enabled' => '1',
      'lunch_break_start'   => '13:00',
      'lunch_break_end'     => '14:00',
    ]));

    $response->assertOk();

    $view = $response->viewData('availabilityPreview');
    $creatableTimes = $view['creatable']->map(fn ($c) => $c['start_at']->format('H:i'))->all();

    $this->assertNotContains('13:00', $creatableTimes);
    $this->assertContains('09:00', $creatableTimes);
    $this->assertContains('12:30', $creatableTimes);

    $skippedReasons = $view['skipped']->pluck('reason')->all();
    $this->assertContains('pausa pranzo', $skippedReasons);
  }
}
