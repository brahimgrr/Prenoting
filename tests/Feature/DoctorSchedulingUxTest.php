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

  public function test_working_hours_template_requires_confirmation_to_cancel_unsupported_appointments(): void
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
    $appointment = $this->appointment($patient, $doctor, $service, $appointmentStart);

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
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool =>
        $confirmation['reason'] === 'Cambio orario lavoro medico'
        && $confirmation['action'] === '/doctor/profile/working-hours'
        && count($confirmation['appointments']) === 1
        && $confirmation['appointments'][0]['id'] === $appointment->id
      );

    $this->assertDatabaseHas('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => $appointmentStart->dayOfWeekIso,
      'start_time' => '09:00',
    ]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
      'cancellation_reason' => null,
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'confirm_appointment_cancellations' => '1',
        'working_hours' => [
          2 => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHas('status', 'Orari ambulatorio aggiornati.');

    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => 'Cambio orario lavoro medico',
    ]);
    $this->assertDatabaseMissing('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => $appointmentStart->dayOfWeekIso,
      'start_time' => '09:00',
    ]);
    $this->assertDatabaseHas('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 2,
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

  public function test_agenda_week_strip_treats_saturday_and_sunday_like_regular_days(): void
  {
    [$doctorUser] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertSee('week-day--selected', false);

    $this->assertStringNotContainsString('week-day--weekend', $response->getContent());
  }

  public function test_past_agenda_days_render_only_appointments_without_slots_or_closures(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $date = CarbonImmutable::now()->subWeek()->startOfDay();
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '11:00',
      'is_active' => true,
    ]);
    $closure = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '09:30',
      'end_time' => '10:00',
      'reason' => 'Riunione passata',
    ]);
    $this->appointment($patient, $doctor, $service, $date->setTime(10, 0));

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertViewHas('daySlots', fn ($slots): bool => $slots->isEmpty())
      ->assertViewHas('dayClosures', fn ($closures): bool => $closures->isEmpty())
      ->assertViewHas('dayEvents', fn ($events): bool => $events->isEmpty())
      ->assertViewHas('upcomingScheduleEvents', fn ($events): bool => $events->isEmpty())
      ->assertSee('patient')
      ->assertSee('Visita dermatologica')
      ->assertDontSee('Slot libero')
      ->assertDontSee('Riunione passata')
      ->assertDontSee('data-agenda-closure-id="'.$closure->id.'"', false);

    $this->assertStringNotContainsString('doctor-agenda-item--free', $response->getContent());
  }

  public function test_closure_overlapping_active_appointment_requires_confirmation_to_cancel_it(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $start = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $appointment = $this->appointment($patient, $doctor, $service, $start);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->post('/doctor/closures', [
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool =>
        $confirmation['reason'] === 'Chiusura straordinaria studio'
        && $confirmation['action'] === '/doctor/closures'
        && count($confirmation['appointments']) === 1
        && $confirmation['appointments'][0]['id'] === $appointment->id
      );

    $this->assertSame(0, $doctor->closures()->count());
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
      'cancellation_reason' => null,
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/closures', [
        'confirm_appointment_cancellations' => '1',
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('status', 'Chiusura creata.');

    $this->assertSame(1, $doctor->closures()->count());
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => 'Chiusura straordinaria studio',
    ]);
  }

  public function test_schedule_confirmation_modal_renders_affected_appointments_and_replays_operation(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $start = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $this->appointment($patient, $doctor, $service, $start);

    $this->actingAs($doctorUser)
      ->followingRedirects()
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->post('/doctor/closures', [
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertOk()
      ->assertSee('id="scheduleConfirmationModal"', false)
      ->assertSee('Conferma chiusura')
      ->assertSee('patient')
      ->assertSee('Visita dermatologica')
      ->assertSee('name="confirm_appointment_cancellations" value="1"', false)
      ->assertSee('action="/doctor/closures"', false)
      ->assertSee('name="date" value="'.$start->toDateString().'"', false)
      ->assertSee('name="all_day" value="1"', false)
      ->assertSee('Chiusura straordinaria studio');
  }

  public function test_creating_larger_closure_replaces_contained_closures(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $first = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '09:30',
      'end_time' => '10:00',
      'reason' => 'Telefonata',
    ]);
    $second = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '11:00',
      'end_time' => '11:30',
      'reason' => 'Pausa',
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
        'reason' => 'Riunione lunga',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHas('status', 'Chiusura creata.');

    $this->assertDatabaseMissing('closures', ['id' => $first->id]);
    $this->assertDatabaseMissing('closures', ['id' => $second->id]);
    $this->assertSame(1, $doctor->closures()->whereDate('date', $date->toDateString())->count());

    $closure = $doctor->closures()->whereDate('date', $date->toDateString())->firstOrFail();
    $this->assertSame('09:00', substr((string) $closure->start_time, 0, 5));
    $this->assertSame('12:00', substr((string) $closure->end_time, 0, 5));
    $this->assertSame('Riunione lunga', $closure->reason);
  }

  public function test_creating_closure_inside_existing_larger_closure_is_rejected(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $existing = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '09:00',
      'end_time' => '12:00',
      'reason' => 'Riunione lunga',
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'reason' => 'Pausa',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHasErrors([
        'closure' => 'Questa chiusura e gia coperta da una chiusura esistente.',
      ]);

    $this->assertSame(1, $doctor->closures()->whereDate('date', $date->toDateString())->count());
    $this->assertDatabaseHas('closures', ['id' => $existing->id]);
  }

  public function test_creating_closure_in_the_past_is_rejected(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->subDay()->startOfDay();

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie passate',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHasErrors([
        'closure' => 'Non puoi creare chiusure in date passate.',
      ]);

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

  public function test_creating_special_opening_in_the_past_is_rejected(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->subDay()->startOfDay();

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/special-openings', [
        'date' => $date->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'note' => 'Apertura passata',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHasErrors([
        'special_opening' => 'Non puoi creare aperture extra in date passate.',
      ]);

    $this->assertSame(0, $doctor->specialOpenings()->count());
  }

  public function test_deleting_special_opening_that_supports_active_appointment_requires_confirmation_to_cancel_it(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->setTime(9, 0);
    $opening = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $sunday->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
    ]);
    $appointment = $this->appointment($patient, $doctor, $service, $sunday);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$sunday->toDateString())
      ->delete("/doctor/special-openings/{$opening->id}")
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool =>
        $confirmation['reason'] === 'Cambio orario lavoro medico'
        && $confirmation['action'] === "/doctor/special-openings/{$opening->id}"
        && $confirmation['method'] === 'DELETE'
        && count($confirmation['appointments']) === 1
        && $confirmation['appointments'][0]['id'] === $appointment->id
      );

    $this->assertDatabaseHas('special_openings', ['id' => $opening->id]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
      'cancellation_reason' => null,
    ]);

    $this->actingAs($doctorUser)
      ->delete("/doctor/special-openings/{$opening->id}", [
        'confirm_appointment_cancellations' => '1',
      ])
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHas('status', 'Apertura extra rimossa.');

    $this->assertDatabaseMissing('special_openings', ['id' => $opening->id]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => 'Cambio orario lavoro medico',
    ]);
  }

  public function test_updating_special_opening_that_supports_active_appointment_requires_confirmation_to_cancel_it(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->setTime(9, 0);
    $opening = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $sunday->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
      'note' => 'Apertura domenicale',
    ]);
    $appointment = $this->appointment($patient, $doctor, $service, $sunday);

    $payload = [
      'date' => $sunday->toDateString(),
      'start_time' => '10:00',
      'end_time' => '11:00',
      'note' => 'Nuovo turno',
    ];

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$sunday->toDateString())
      ->patch("/doctor/special-openings/{$opening->id}", $payload)
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool =>
        $confirmation['reason'] === 'Cambio orario lavoro medico'
        && $confirmation['action'] === "/doctor/special-openings/{$opening->id}"
        && $confirmation['method'] === 'PATCH'
        && $confirmation['payload']['start_time'] === '10:00'
        && count($confirmation['appointments']) === 1
        && $confirmation['appointments'][0]['id'] === $appointment->id
      );

    $this->assertDatabaseHas('special_openings', [
      'id' => $opening->id,
      'start_time' => '09:00',
      'end_time' => '10:00',
    ]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
      'cancellation_reason' => null,
    ]);

    $this->actingAs($doctorUser)
      ->patch("/doctor/special-openings/{$opening->id}", $payload + [
        'confirm_appointment_cancellations' => '1',
      ])
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHas('status', 'Apertura extra aggiornata.');

    $this->assertDatabaseHas('special_openings', [
      'id' => $opening->id,
      'start_time' => '10:00',
      'end_time' => '11:00',
    ]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => 'Cambio orario lavoro medico',
    ]);
  }

  public function test_deleting_closure_that_overlaps_active_appointment_requires_confirmation_to_cancel_it(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $start = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $closure = $doctor->closures()->create([
      'date' => $start->toDateString(),
      'start_time' => '08:00',
      'end_time' => '10:00',
      'reason' => 'Chiusura',
    ]);
    $appointment = $this->appointment($patient, $doctor, $service, $start);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->delete("/doctor/closures/{$closure->id}")
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool =>
        $confirmation['reason'] === 'Chiusura straordinaria studio'
        && $confirmation['action'] === "/doctor/closures/{$closure->id}"
        && $confirmation['method'] === 'DELETE'
        && count($confirmation['appointments']) === 1
        && $confirmation['appointments'][0]['id'] === $appointment->id
      );

    $this->assertDatabaseHas('closures', ['id' => $closure->id]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
      'cancellation_reason' => null,
    ]);

    $this->actingAs($doctorUser)
      ->delete("/doctor/closures/{$closure->id}", [
        'confirm_appointment_cancellations' => '1',
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('status', 'Chiusura rimossa.');

    $this->assertDatabaseMissing('closures', ['id' => $closure->id]);
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => 'Chiusura straordinaria studio',
    ]);
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
