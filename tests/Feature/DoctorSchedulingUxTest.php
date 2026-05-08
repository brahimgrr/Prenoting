<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\SpecialOpening;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\AvailabilityService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DoctorSchedulingUxTest extends TestCase
{
  use RefreshDatabase;

  public function test_doctor_profile_shows_and_saves_forever_working_hours_template(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '08:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('Orari ambulatorio')
      ->assertSee('name="working_hours[1][0][start_time]"', false)
      ->assertSee('08:00');

    $content = $this->actingAs($doctorUser)->get('/doctor/profile')->getContent();
    $this->assertSelectStartsWithOptions($content, 'working_hours[1][0][start_time]', [
      '<option value="08:00" selected>08:00</option>',
      '<option value="">--:--</option>',
    ]);

    $this->actingAs($doctorUser)
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            ['start_time' => '09:00', 'end_time' => '13:00'],
            ['start_time' => '14:00', 'end_time' => '18:00'],
          ],
          6 => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile');

    $this->assertSame(3, $doctor->workingHours()->count());
    $this->assertDatabaseMissing('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '08:00',
    ]);
    $this->assertDatabaseHas('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '09:00',
      'end_time' => '13:00',
      'effective_from' => null,
      'effective_until' => null,
      'is_active' => true,
    ]);
    $this->assertDatabaseHas('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 6,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'effective_from' => null,
      'effective_until' => null,
      'is_active' => true,
    ]);
  }

  public function test_doctor_profile_defaults_empty_weekday_rows_to_morning_window_and_weekends_blank(): void
  {
    [$doctorUser] = $this->doctorContext();

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('name="working_hours[1][0][start_time]"', false)
      ->assertSee('<option value="09:00" selected>09:00</option>', false)
      ->assertSee('name="working_hours[1][0][end_time]"', false)
      ->assertSee('<option value="12:00" selected>12:00</option>', false);

    $content = $response->getContent();
    $this->assertSelectStartsWithOptions($content, 'working_hours[6][0][start_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
    $this->assertSelectStartsWithOptions($content, 'working_hours[7][0][end_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
  }

  public function test_time_forms_render_half_hour_selects_instead_of_native_time_inputs(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '08:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);
    SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $date->addWeek()->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
      'note' => 'Open day',
    ]);

    $profile = $this->actingAs($doctorUser)->get('/doctor/profile');
    $profile->assertOk()
      ->assertSee('<select', false)
      ->assertSee('name="working_hours[1][0][start_time]"', false)
      ->assertSee('<option value="08:00" selected>08:00</option>', false)
      ->assertSee('<option value="08:30"', false)
      ->assertDontSee('type="time"', false)
      ->assertDontSee('<option value="08:15"', false);

    $agenda = $this->actingAs($doctorUser)->get('/doctor/agenda?date='.$date->toDateString());
    $agenda->assertOk()
      ->assertSee('<select', false)
      ->assertSee('id="closure-start-time"', false)
      ->assertSee('id="special-opening-start"', false)
      ->assertSee('name="start_time"', false)
      ->assertSee('<option value="09:00" selected>09:00</option>', false)
      ->assertSee('<option value="09:30"', false)
      ->assertDontSee('type="time"', false)
      ->assertDontSee('<option value="09:15"', false);
  }

  public function test_working_hours_template_rejects_overlaps_and_non_grid_times(): void
  {
    [$doctorUser] = $this->doctorContext();

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
            ['start_time' => '11:30', 'end_time' => '13:00'],
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHasErrors('working_hours');

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            ['start_time' => '09:15', 'end_time' => '12:00'],
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHasErrors('working_hours');
  }

  public function test_working_hours_template_rejects_changes_that_exclude_active_appointments(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $appointmentStart = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(10, 0);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $appointmentStart->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);
    $this->appointment($patient, $doctor, $service, $appointmentStart);

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          2 => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHasErrors('working_hours');

    $this->assertDatabaseHas('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => $appointmentStart->dayOfWeekIso,
      'start_time' => '09:00',
    ]);
  }

  public function test_agenda_creates_full_day_and_partial_closures(): void
  {
    [$doctorUser, $doctor,, $service] = $this->doctorContext(withPatient: true);
    $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $monday->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/closures', [
        'date' => $monday->toDateString(),
        'end_date' => $monday->addDay()->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertRedirect('/doctor/agenda?date='.$monday->toDateString());

    $this->assertSame(2, $doctor->closures()->count());
    $fullDayClosure = $doctor->closures()->whereDate('date', $monday->toDateString())->firstOrFail();
    $this->assertNull($fullDayClosure->start_time);
    $this->assertNull($fullDayClosure->end_time);
    $this->assertSame('Ferie', $fullDayClosure->reason);
    $this->assertCount(0, app(AvailabilityService::class)->availableSlotsForDate($doctor, $service, $monday));

    $partialDate = $monday->addWeek();
    $this->actingAs($doctorUser)
      ->post('/doctor/closures', [
        'date' => $partialDate->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'reason' => 'Riunione',
      ])
      ->assertRedirect('/doctor/agenda?date='.$partialDate->toDateString());

    $partialClosure = $doctor->closures()->whereDate('date', $partialDate->toDateString())->firstOrFail();
    $this->assertSame('10:00', substr((string) $partialClosure->start_time, 0, 5));
    $this->assertSame('11:00', substr((string) $partialClosure->end_time, 0, 5));
    $this->assertSame('Riunione', $partialClosure->reason);
  }

  public function test_agenda_renders_multi_slot_closure_as_single_continuous_block(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '13:00',
      'is_active' => true,
    ]);
    $closure = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '10:00',
      'end_time' => '12:00',
      'reason' => 'Disponibilita bloccata',
    ]);

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertSee('data-agenda-closure-id="'.$closure->id.'"', false)
      ->assertSee('style="--agenda-span-rows: 4;"', false)
      ->assertSee('10:00 - 12:00');

    $content = $response->getContent();
    $this->assertSame(1, substr_count($content, 'data-agenda-closure-id="'.$closure->id.'"'));
    $this->assertStringNotContainsString('data-agenda-closure-segment="1"', $content);
  }

  public function test_closure_overlapping_active_appointment_is_rejected(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $start = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $this->appointment($patient, $doctor, $service, $start);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->post('/doctor/closures', [
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHasErrors('closure');

    $this->assertSame(0, $doctor->closures()->count());
  }

  public function test_special_opening_generates_closed_day_slots_and_rejects_redundant_windows(): void
  {
    [$doctorUser, $doctor,, $service] = $this->doctorContext(withPatient: true);
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY);

    $this->actingAs($doctorUser)
      ->post('/doctor/special-openings', [
        'date' => $sunday->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'note' => 'Apertura domenicale',
      ])
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString());

    $slots = app(AvailabilityService::class)->availableSlotsForDate($doctor, $service, $sunday);
    $this->assertSame(['09:00', '09:30'], $slots->map(fn ($slot) => $slot->start_at->format('H:i'))->all());

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$sunday->toDateString())
      ->post('/doctor/special-openings', [
        'date' => $sunday->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'note' => 'Duplicata',
      ])
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHasErrors('special_opening');
  }

  public function test_deleting_special_opening_that_supports_active_appointment_is_rejected(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->setTime(9, 0);
    $opening = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $sunday->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
    ]);
    $this->appointment($patient, $doctor, $service, $sunday);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$sunday->toDateString())
      ->delete("/doctor/special-openings/{$opening->id}")
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHasErrors('special_opening');

    $this->assertDatabaseHas('special_openings', ['id' => $opening->id]);
  }

  public function test_agenda_renders_exception_actions_and_upcoming_events_without_batch_availability(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::SATURDAY);
    $opening = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $date->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
      'note' => 'Open day',
    ]);
    $closure = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '11:00',
      'end_time' => '12:00',
      'reason' => 'Riunione',
    ]);

    $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertSee('Chiusura')
      ->assertSee('Apertura extra')
      ->assertSee('Eventi del giorno')
      ->assertSee('Prossimi eventi')
      ->assertSee('Open day')
      ->assertSee('Riunione')
      ->assertSee("action=\"/doctor/special-openings/{$opening->id}\"", false)
      ->assertSee("action=\"/doctor/closures/{$closure->id}\"", false)
      ->assertDontSee('Crea disponibilita')
      ->assertDontSee('/doctor/availability/batch', false);
  }

  private function doctorContext(bool $withPatient = false): array
  {
    $doctorUser = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
      'is_active' => true,
    ]);

    if (! $withPatient) {
      return [$doctorUser, $doctor];
    }

    $patientUser = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $patient = PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);
    $service = MedicalService::create(['name' => 'Visita dermatologica', 'duration_minutes' => 30]);

    return [$doctorUser, $doctor, $patient, $service];
  }

  private function appointment(
    PatientProfile $patient,
    DoctorProfile $doctor,
    MedicalService $service,
    CarbonImmutable $start,
  ): Appointment {
    return Appointment::create([
      'patient_id' => $patient->id,
      'doctor_profile_id' => $doctor->id,
      'service_id' => $service->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
      'status' => Appointment::STATUS_CONFIRMED,
    ]);
  }

  private function assertSelectStartsWithOptions(string $content, string $name, array $expectedOptions): void
  {
    $quotedName = preg_quote($name, '/');
    $this->assertSame(1, preg_match('/<select\b[^>]*name="'.$quotedName.'"[^>]*>[\s\S]*?<\/select>/', $content, $matches));

    $options = substr($matches[0], strpos($matches[0], '>') + 1);
    $options = preg_replace('/\s+/', '', $options);
    $expected = preg_replace('/\s+/', '', implode('', $expectedOptions));

    $this->assertStringStartsWith($expected, $options);
  }
}
