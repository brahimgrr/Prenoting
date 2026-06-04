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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DoctorSchedulingUxTest extends TestCase
{
  use RefreshDatabase;

  public function test_doctor_profile_shows_and_saves_simplified_working_hours_template(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '08:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('Orari ambulatorio')
      ->assertSee('Orari di apertura', false)
      ->assertSee('Apri alle')
      ->assertSee('Chiudi alle')
      ->assertSee('Pausa pranzo')
      ->assertDontSee('data-working-hours-remove', false)
      ->assertSee('name="working_hours[1][open_time]"', false)
      ->assertSee('08:00');

    $content = $response->getContent();
    $this->assertSelectStartsWithOptions($content, 'working_hours[1][open_time]', [
      '<option value="08:00" selected>08:00</option>',
      '<option value="">--:--</option>',
    ]);

    $this->actingAs($doctorUser)
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            'open_time' => '09:00',
            'break_start_time' => '13:00',
            'break_end_time' => '14:00',
            'close_time' => '18:00',
          ],
          6 => [
            'open_time' => '09:00',
            'close_time' => '12:00',
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
      'weekday' => 1,
      'start_time' => '14:00',
      'end_time' => '18:00',
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

  public function test_doctor_profile_keeps_empty_working_hour_rows_blank(): void
  {
    [$doctorUser] = $this->doctorContext();

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('name="working_hours[1][open_time]"', false)
      ->assertSee('name="working_hours[1][close_time]"', false);

    $content = $response->getContent();
    $this->assertSelectStartsWithOptions($content, 'working_hours[1][open_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
    $this->assertSelectStartsWithOptions($content, 'working_hours[1][close_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
    $this->assertSelectStartsWithOptions($content, 'working_hours[6][open_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
    $this->assertSelectStartsWithOptions($content, 'working_hours[7][close_time]', [
      '<option value="">--:--</option>',
      '<option value="00:00" >00:00</option>',
    ]);
  }

  public function test_doctor_profile_renders_reset_buttons_for_opening_hours_and_lunch_break(): void
  {
    [$doctorUser] = $this->doctorContext();

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('data-working-hours-reset-day', false)
      ->assertSee('data-working-hours-reset-break', false)
      ->assertSee('data-working-hours-add-break', false)
      ->assertSee('data-working-hours-break-row', false)
      ->assertSee('Aggiungi pausa pranzo')
      ->assertSee('working-hours-reset-button', false)
      ->assertSee('class="col-12 col-md-auto d-flex align-items-end justify-content-md-end"', false)
      ->assertSee('aria-label="Rimuovi apertura Lunedi"', false)
      ->assertSee('aria-label="Rimuovi pausa pranzo Lunedi"', false)
      ->getContent();

    $this->assertSame(7, preg_match_all('/<button[^>]+data-working-hours-reset-day/', $content));
    $this->assertSame(7, preg_match_all('/<button[^>]+data-working-hours-reset-break/', $content));
    $this->assertSame(7, preg_match_all('/<button[^>]+data-working-hours-add-break/', $content));
    $this->assertMatchesRegularExpression('/<button[^>]*data-working-hours-add-break[^>]*aria-controls="working-hours-1-break-row"[^>]*>\\s*Aggiungi pausa pranzo\\s*<\\/button>/s', $content);
    $this->assertMatchesRegularExpression('/<div[^>]*id="working-hours-1-break-row"[^>]*data-working-hours-break-row[^>]*hidden/s', $content);
    $this->assertStringContainsString('function resettaRigaOrarioGiorno', $content);
    $this->assertStringContainsString('function resettaRigaPausaPranzo', $content);
    $this->assertStringContainsString('function mostraRigaPausaPranzo', $content);
    $this->assertStringContainsString('function nascondiRigaPausaPranzo', $content);
    $this->assertStringContainsString('const addBreakButton = event.target.closest("[data-working-hours-add-break]")', $content);
    $this->assertStringContainsString('const resetDayButton = event.target.closest("[data-working-hours-reset-day]")', $content);
    $this->assertStringContainsString('const resetBreakButton = event.target.closest("[data-working-hours-reset-break]")', $content);
  }

  public function test_doctor_profile_disables_lunch_break_button_for_closed_days(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '09:00',
      'end_time' => '18:00',
      'is_active' => true,
    ]);

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->getContent();

    $this->assertMatchesRegularExpression('/<button(?=[^>]*data-working-hours-add-break)(?=[^>]*aria-controls="working-hours-1-break-row")(?![^>]*disabled)[^>]*>/s', $content);
    $this->assertMatchesRegularExpression('/<button(?=[^>]*data-working-hours-add-break)(?=[^>]*aria-controls="working-hours-2-break-row")(?=[^>]*disabled)[^>]*>/s', $content);
  }

  public function test_doctor_profile_shows_lunch_break_row_when_lunch_break_exists(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '09:00',
      'end_time' => '13:00',
      'is_active' => true,
    ]);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '14:00',
      'end_time' => '18:00',
      'is_active' => true,
    ]);

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->getContent();

    $this->assertMatchesRegularExpression('/<button[^>]*data-working-hours-add-break[^>]*hidden[^>]*aria-controls="working-hours-1-break-row"/s', $content);
    $this->assertMatchesRegularExpression('/<div[^>]*id="working-hours-1-break-row"[^>]*data-working-hours-break-row(?![^>]*hidden)/s', $content);
    $this->assertStringContainsString('<option value="13:00" selected>13:00</option>', $this->selectByName($content, 'working_hours[1][break_start_time]'));
    $this->assertStringContainsString('<option value="14:00" selected>14:00</option>', $this->selectByName($content, 'working_hours[1][break_end_time]'));
  }

  public function test_doctor_profile_hides_working_hours_editor_until_editing(): void
  {
    [$doctorUser] = $this->doctorContext();

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->assertSee('data-working-hours-summary', false)
      ->assertSee('data-working-hours-edit', false)
      ->assertSee('Modifica')
      ->assertSee('id="working-hours-editor"', false)
      ->getContent();

    $this->assertMatchesRegularExpression('/<div[^>]*id="working-hours-editor"[^>]*hidden[^>]*data-working-hours-editor/s', $content);
    $this->assertStringContainsString('function mostraEditorOrariAmbulatorio', $content);
    $this->assertStringContainsString('const editButton = event.target.closest("[data-working-hours-edit]")', $content);
  }

  public function test_doctor_profile_keeps_working_hours_editor_open_after_validation_errors(): void
  {
    [$doctorUser] = $this->doctorContext();

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            'open_time' => '09:00',
            'break_start_time' => '13:00',
            'close_time' => '18:00',
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHasErrors('working_hours');

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->getContent();

    $this->assertDoesNotMatchRegularExpression('/<div[^>]*id="working-hours-editor"[^>]*hidden[^>]*data-working-hours-editor/s', $content);
    $this->assertMatchesRegularExpression('/<button[^>]*data-working-hours-edit[^>]*hidden/s', $content);
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
      ->assertSee('name="working_hours[1][open_time]"', false)
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

  public function test_time_forms_offer_midnight_as_an_end_time_only(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);

    $profileContent = $this->actingAs($doctorUser)
      ->get('/doctor/profile')
      ->assertOk()
      ->getContent();

    $this->assertSelectDoesNotContainOption($profileContent, 'working_hours[1][open_time]', '24:00');
    $this->assertSelectContainsOption($profileContent, 'working_hours[1][close_time]', '24:00');

    $agendaContent = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->getContent();

    $this->assertSelectByIdContainsOption($agendaContent, 'closure-end-time', '24:00');
    $this->assertSelectByIdContainsOption($agendaContent, 'special-opening-end', '24:00');
    $this->assertSelectByIdDoesNotContainOption($agendaContent, 'closure-start-time', '24:00');
    $this->assertSelectByIdDoesNotContainOption($agendaContent, 'special-opening-start', '24:00');
  }

  public function test_closure_all_day_toggle_controls_time_fields_consistently(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->getContent();

    $this->assertStringContainsString('data-closure-all-day-toggle', $content);
    $this->assertStringContainsString('data-closure-time-field', $this->selectById($content, 'closure-start-time'));
    $this->assertStringContainsString('data-closure-time-field', $this->selectById($content, 'closure-end-time'));
    $this->assertStringContainsString('disabled', $this->selectById($content, 'closure-start-time'));
    $this->assertStringContainsString('disabled', $this->selectById($content, 'closure-end-time'));
    $this->assertStringContainsString('function sincronizzaCampiOrarioChiusura', $content);
    $this->assertStringContainsString('allDayToggle.checked = false', $content);
  }

  public function test_doctor_can_save_working_hours_until_midnight_and_generate_the_last_slot(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

    $this->actingAs($doctorUser)
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          $date->dayOfWeekIso => [
            'open_time' => '23:00',
            'close_time' => '24:00',
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHas('status', 'Orari ambulatorio aggiornati.');

    $workingHour = $doctor->workingHours()->firstOrFail();
    $this->assertSame('23:00', substr((string) $workingHour->start_time, 0, 5));
    $this->assertSame('24:00', substr((string) $workingHour->end_time, 0, 5));

    $slots = app(AvailabilityService::class)->agendaSlotsForDate($doctor, $date);

    $this->assertSame(['23:00', '23:30'], $slots->map(fn ($slot) => $slot->start_at->format('H:i'))->all());
    $this->assertSame('00:00', $slots->last()->end_at->format('H:i'));
    $this->assertSame($date->addDay()->toDateString(), $slots->last()->end_at->toDateString());
  }

  public function test_agenda_can_create_and_render_partial_closure_until_midnight(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '22:00',
      'end_time' => '24:00',
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'start_time' => '22:00',
        'end_time' => '24:00',
        'reason' => 'Chiusura serale',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHas('status', 'Chiusura creata.');

    $closure = $doctor->closures()->firstOrFail();
    $this->assertSame('22:00', substr((string) $closure->start_time, 0, 5));
    $this->assertSame('24:00', substr((string) $closure->end_time, 0, 5));

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertSee('data-agenda-closure-id="'.$closure->id.'"', false)
      ->assertSee('style="--agenda-span-rows: 4;"', false)
      ->assertSee('22:00 - 00:00');

    $this->assertStringNotContainsString('22:00 - 23:30', $response->getContent());
  }

  public function test_doctor_can_block_the_last_generated_slot_before_midnight(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $date->dayOfWeekIso,
      'start_time' => '23:00',
      'end_time' => '24:00',
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/availability/block', [
        'slot_start' => $date->setTime(23, 30)->format('Y-m-d\TH:i'),
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHas('status', 'Disponibilita bloccata.');

    $closure = $doctor->closures()->firstOrFail();
    $this->assertSame($date->toDateString(), $closure->date->toDateString());
    $this->assertSame('23:30', substr((string) $closure->start_time, 0, 5));
    $this->assertSame('24:00', substr((string) $closure->end_time, 0, 5));

    $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertSee('23:30 - 00:00');
  }

  public function test_working_hours_template_rejects_overlaps_and_non_grid_times(): void
  {
    [$doctorUser] = $this->doctorContext();

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            'open_time' => '09:00',
            'break_start_time' => '12:00',
            'break_end_time' => '11:30',
            'close_time' => '13:00',
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
            'open_time' => '09:15',
            'close_time' => '12:00',
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHasErrors('working_hours');
  }

  public function test_working_hours_template_rejects_incomplete_lunch_break(): void
  {
    [$doctorUser] = $this->doctorContext();

    $this->actingAs($doctorUser)
      ->from('/doctor/profile')
      ->patch('/doctor/profile/working-hours', [
        'working_hours' => [
          1 => [
            'open_time' => '09:00',
            'break_start_time' => '13:00',
            'close_time' => '18:00',
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
            'open_time' => '09:00',
            'close_time' => '12:00',
          ],
        ],
      ])
      ->assertRedirect('/doctor/profile')
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool => $confirmation['reason'] === 'Cambio orario lavoro medico'
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
            'open_time' => '09:00',
            'close_time' => '12:00',
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
      ->assertSee('week-strip week-strip--agenda d-flex gap-2 flex-fill overflow-auto p-1', false)
      ->assertSee('week-day d-flex flex-column align-items-center justify-content-center gap-1 text-center p-2 week-day--selected border-primary bg-primary bg-opacity-10', false);

    $this->assertStringNotContainsString('week-day--weekend', $response->getContent());
  }

  public function test_agenda_week_strip_colors_days_by_booked_open_and_closed_state(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $weekStart = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->startOfDay();
    $closedDay = $weekStart;
    $openDay = $weekStart->addDay();
    $bookedDay = $weekStart->addDays(2);

    foreach ([$openDay, $bookedDay] as $workingDay) {
      WorkingHour::create([
        'doctor_profile_id' => $doctor->id,
        'weekday' => $workingDay->dayOfWeekIso,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_active' => true,
      ]);
    }

    $this->appointment($patient, $doctor, $service, $bookedDay->setTime(9, 0));

    $content = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$closedDay->toDateString().'&week_start='.$weekStart->toDateString())
      ->assertOk()
      ->getContent();

    $this->assertAgendaDayDotState($content, $closedDay, 'closed');
    $this->assertAgendaDayDotState($content, $openDay, 'open');
    $this->assertAgendaDayDotState($content, $bookedDay, 'booked');
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
    $futureClosure = $doctor->closures()->create([
      'date' => CarbonImmutable::now()->addWeek()->toDateString(),
      'start_time' => '11:00',
      'end_time' => '12:00',
      'reason' => 'Congresso',
    ]);
    $this->appointment($patient, $doctor, $service, $date->setTime(10, 0));

    $response = $this->actingAs($doctorUser)
      ->get('/doctor/agenda?date='.$date->toDateString())
      ->assertOk()
      ->assertViewHas('daySlots', fn ($slots): bool => $slots->isEmpty())
      ->assertViewHas('dayClosures', fn ($closures): bool => $closures->isEmpty())
      ->assertViewHas('upcomingScheduleEvents', fn ($events): bool => $events->contains(
        fn (array $event): bool => $event['type'] === 'closure' && $event['model']->is($futureClosure)
      ))
      ->assertSee('patient@example.com')
      ->assertSee('Visita dermatologica')
      ->assertSee('Congresso')
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
    $cancellationReason = 'Formazione fuori sede';

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->post('/doctor/closures', [
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => $cancellationReason,
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool => $confirmation['reason'] === $cancellationReason
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
        'reason' => $cancellationReason,
      ])
      ->assertRedirect('/doctor/agenda?date='.$start->toDateString())
      ->assertSessionHas('status', 'Chiusura creata.');

    $this->assertSame(1, $doctor->closures()->count());
    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CANCELLED,
      'cancellation_reason' => $cancellationReason,
    ]);
  }

  public function test_schedule_confirmation_modal_renders_affected_appointments_and_replays_operation(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $start = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $this->appointment($patient, $doctor, $service, $start);
    $cancellationReason = 'Ferie';

    $this->actingAs($doctorUser)
      ->followingRedirects()
      ->from('/doctor/agenda?date='.$start->toDateString())
      ->post('/doctor/closures', [
        'date' => $start->toDateString(),
        'all_day' => '1',
        'reason' => $cancellationReason,
      ])
      ->assertOk()
      ->assertSee('id="scheduleConfirmationModal"', false)
      ->assertSee('Conferma chiusura')
      ->assertSee('patient@example.com')
      ->assertSee('Visita dermatologica')
      ->assertSee('name="confirm_appointment_cancellations" value="1"', false)
      ->assertSee('action="/doctor/closures"', false)
      ->assertSee('name="date" value="'.$start->toDateString().'"', false)
      ->assertSee('name="all_day" value="1"', false)
      ->assertSee($cancellationReason)
      ->assertDontSee('Chiusura straordinaria studio');
  }

  public function test_creating_larger_closure_that_contains_existing_closures_is_rejected(): void
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
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
        'reason' => 'Riunione lunga',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHasErrors([
        'closure' => 'Questa chiusura si sovrappone a una chiusura esistente.',
      ]);

    $this->assertSame(2, $doctor->closures()->whereDate('date', $date->toDateString())->count());
    $this->assertDatabaseHas('closures', ['id' => $first->id]);
    $this->assertDatabaseHas('closures', ['id' => $second->id]);
  }

  public function test_multi_day_closure_with_any_overlapping_day_is_rejected_without_partial_creation(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $overlappingDate = $startDate->addDay();
    $existing = $doctor->closures()->create([
      'date' => $overlappingDate->toDateString(),
      'start_time' => null,
      'end_time' => null,
      'reason' => 'Ferie esistenti',
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$startDate->toDateString())
      ->post('/doctor/closures', [
        'date' => $startDate->toDateString(),
        'end_date' => $startDate->addDays(2)->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertRedirect('/doctor/agenda?date='.$startDate->toDateString())
      ->assertSessionHasErrors([
        'closure' => 'Questa chiusura si sovrappone a una chiusura esistente.',
      ]);

    $this->assertSame(0, $doctor->closures()->whereDate('date', $startDate->toDateString())->count());
    $this->assertDatabaseHas('closures', ['id' => $existing->id]);
  }

  public function test_overlapping_multi_day_closure_shows_closure_error_before_appointment_confirmation(): void
  {
    [$doctorUser, $doctor, $patient, $service] = $this->doctorContext(withPatient: true);
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $appointment = $this->appointment($patient, $doctor, $service, $startDate->setTime(9, 0));
    $overlappingDate = $startDate->addDay();
    $existing = $doctor->closures()->create([
      'date' => $overlappingDate->toDateString(),
      'start_time' => null,
      'end_time' => null,
      'reason' => 'Ferie esistenti',
    ]);

    $this->actingAs($doctorUser)
      ->followingRedirects()
      ->from('/doctor/agenda?date='.$startDate->toDateString())
      ->post('/doctor/closures', [
        'date' => $startDate->toDateString(),
        'end_date' => $startDate->addDays(2)->toDateString(),
        'all_day' => '1',
        'reason' => 'Ferie',
      ])
      ->assertOk()
      ->assertSee('Questa chiusura si sovrappone a una chiusura esistente.')
      ->assertDontSee('id="scheduleConfirmationModal"', false);

    $this->assertDatabaseHas('appointments', [
      'id' => $appointment->id,
      'status' => Appointment::STATUS_CONFIRMED,
    ]);
    $this->assertSame(0, $doctor->closures()->whereDate('date', $startDate->toDateString())->count());
    $this->assertDatabaseHas('closures', ['id' => $existing->id]);
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
        'closure' => 'Questa chiusura si sovrappone a una chiusura esistente.',
      ]);

    $this->assertSame(1, $doctor->closures()->whereDate('date', $date->toDateString())->count());
    $this->assertDatabaseHas('closures', ['id' => $existing->id]);
  }

  public function test_creating_closure_that_partially_overlaps_existing_closure_is_rejected(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $existing = $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '09:00',
      'end_time' => '11:00',
      'reason' => 'Riunione',
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$date->toDateString())
      ->post('/doctor/closures', [
        'date' => $date->toDateString(),
        'start_time' => '10:30',
        'end_time' => '12:00',
        'reason' => 'Pausa',
      ])
      ->assertRedirect('/doctor/agenda?date='.$date->toDateString())
      ->assertSessionHasErrors([
        'closure' => 'Questa chiusura si sovrappone a una chiusura esistente.',
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

  public function test_creating_special_opening_that_overlaps_existing_special_opening_is_rejected(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY);
    $existing = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $sunday->toDateString(),
      'start_time' => '09:00',
      'end_time' => '11:00',
      'note' => 'Apertura domenicale',
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/agenda?date='.$sunday->toDateString())
      ->post('/doctor/special-openings', [
        'date' => $sunday->toDateString(),
        'start_time' => '10:30',
        'end_time' => '12:00',
        'note' => 'Secondo turno',
      ])
      ->assertRedirect('/doctor/agenda?date='.$sunday->toDateString())
      ->assertSessionHasErrors([
        'special_opening' => 'Questa apertura extra si sovrappone a un\'apertura extra esistente.',
      ]);

    $this->assertSame(1, $doctor->specialOpenings()->whereDate('date', $sunday->toDateString())->count());
    $this->assertDatabaseHas('special_openings', ['id' => $existing->id]);
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
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool => $confirmation['reason'] === 'Cambio orario lavoro medico'
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

  public function test_exception_events_do_not_accept_update_requests(): void
  {
    [$doctorUser, $doctor] = $this->doctorContext();
    $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY);
    $opening = SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $sunday->toDateString(),
      'start_time' => '09:00',
      'end_time' => '10:00',
      'note' => 'Apertura domenicale',
    ]);
    $closure = $doctor->closures()->create([
      'date' => $sunday->toDateString(),
      'start_time' => '11:00',
      'end_time' => '12:00',
      'reason' => 'Chiusura domenicale',
    ]);

    $this->actingAs($doctorUser)
      ->patch("/doctor/special-openings/{$opening->id}", [
        'date' => $sunday->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'note' => 'Nuovo turno',
      ])
      ->assertStatus(405);

    $this->actingAs($doctorUser)
      ->patch("/doctor/closures/{$closure->id}", [
        'date' => $sunday->toDateString(),
        'start_time' => '12:00',
        'end_time' => '13:00',
        'reason' => 'Nuova chiusura',
      ])
      ->assertStatus(405);

    $this->assertDatabaseHas('special_openings', [
      'id' => $opening->id,
      'start_time' => '09:00',
      'end_time' => '10:00',
    ]);
    $this->assertDatabaseHas('closures', [
      'id' => $closure->id,
      'start_time' => '11:00',
      'end_time' => '12:00',
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
      ->assertSessionHas('schedule_confirmation', fn (array $confirmation): bool => $confirmation['reason'] === 'Chiusura straordinaria studio'
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
      ->assertDontSee('Eventi del giorno')
      ->assertSee('Prossimi eventi')
      ->assertSee('Open day')
      ->assertSee('Riunione')
      ->assertDontSee('aria-label="Modifica evento"', false)
      ->assertDontSee('Modifica chiusura')
      ->assertDontSee('Modifica apertura extra')
      ->assertDontSee('value="PATCH"', false)
      ->assertSee("data-bs-target=\"#deleteScheduleEventModalSpecialOpening{$opening->id}\"", false)
      ->assertSee("data-bs-target=\"#deleteScheduleEventModalClosure{$closure->id}\"", false)
      ->assertSee("id=\"deleteScheduleEventModalSpecialOpening{$opening->id}\"", false)
      ->assertSee("id=\"deleteScheduleEventModalClosure{$closure->id}\"", false)
      ->assertSee('Conferma eliminazione')
      ->assertSee('Questa apertura extra verrà eliminata.')
      ->assertSee('Questa chiusura verrà eliminata.')
      ->assertSee("action=\"/doctor/special-openings/{$opening->id}\"", false)
      ->assertSee("action=\"/doctor/closures/{$closure->id}\"", false)
      ->assertDontSee('Crea disponibilita')
      ->assertDontSee('/doctor/availability/batch', false);
  }

  public function test_agenda_orders_upcoming_events_chronologically_across_event_types(): void
  {
    $this->travelTo(CarbonImmutable::parse('2026-05-26 09:00:00'));

    [$doctorUser, $doctor] = $this->doctorContext();

    SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => '2026-05-30',
      'start_time' => '09:00',
      'end_time' => '12:00',
    ]);
    SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => '2026-05-31',
      'start_time' => '09:00',
      'end_time' => '12:00',
    ]);
    $doctor->closures()->create([
      'date' => '2026-06-07',
      'start_time' => null,
      'end_time' => null,
    ]);
    SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => '2026-06-06',
      'start_time' => '11:00',
      'end_time' => '13:00',
      'note' => 'b',
    ]);
    SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => '2026-06-06',
      'start_time' => '09:00',
      'end_time' => '12:00',
      'note' => 'a',
    ]);
    $doctor->closures()->create([
      'date' => '2026-06-02',
      'start_time' => null,
      'end_time' => null,
      'reason' => 'congresso medico',
    ]);
    $doctor->closures()->create([
      'date' => '2026-05-29',
      'start_time' => null,
      'end_time' => null,
    ]);

    $this->actingAs($doctorUser)
      ->get('/doctor/agenda')
      ->assertOk()
      ->assertViewHas('upcomingScheduleEvents', function ($events): bool {
        return $events
          ->map(fn (array $event): string => implode('|', [
            $event['title'],
            $event['date']->toDateString(),
            $event['start_time'] ?? '00:00',
          ]))
          ->all() === [
            'Chiusura|2026-05-29|00:00',
            'Apertura extra|2026-05-30|09:00',
            'Apertura extra|2026-05-31|09:00',
            'congresso medico|2026-06-02|00:00',
            'a|2026-06-06|09:00',
            'b|2026-06-06|11:00',
            'Chiusura|2026-06-07|00:00',
          ];
      });
  }

  private function doctorContext(bool $withPatient = false): array
  {
    $doctorUser = User::create([
      'email' => 'doctor.derm@example.com',
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
      'email' => 'patient@example.com',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $patient = PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);
    $service = MedicalService::create([
      'doctor_profile_id' => $doctor->id,
      'name' => 'Visita dermatologica',
      'duration_minutes' => 30,
    ]);

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
      'service_id' => $service->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
      'status' => Appointment::STATUS_CONFIRMED,
    ]);
  }

  private function assertSelectStartsWithOptions(string $content, string $name, array $expectedOptions): void
  {
    $options = substr($this->selectByName($content, $name), strpos($this->selectByName($content, $name), '>') + 1);
    $options = preg_replace('/\s+/', '', $options);
    $expected = preg_replace('/\s+/', '', implode('', $expectedOptions));

    $this->assertStringStartsWith($expected, $options);
  }

  private function assertSelectContainsOption(string $content, string $name, string $value): void
  {
    $this->assertStringContainsString(
      '<option value="'.$value.'">'.$value.'</option>',
      $this->selectByName($content, $name),
    );
  }

  private function assertSelectDoesNotContainOption(string $content, string $name, string $value): void
  {
    $this->assertStringNotContainsString(
      '<option value="'.$value.'">'.$value.'</option>',
      $this->selectByName($content, $name),
    );
  }

  private function assertSelectByIdContainsOption(string $content, string $id, string $value): void
  {
    $this->assertStringContainsString(
      '<option value="'.$value.'">'.$value.'</option>',
      $this->selectById($content, $id),
    );
  }

  private function assertSelectByIdDoesNotContainOption(string $content, string $id, string $value): void
  {
    $this->assertStringNotContainsString(
      '<option value="'.$value.'">'.$value.'</option>',
      $this->selectById($content, $id),
    );
  }

  private function assertAgendaDayDotState(string $content, CarbonImmutable $date, string $state): void
  {
    $quotedDate = preg_quote($date->toDateString(), '/');
    $quotedState = preg_quote($state, '/');

    $this->assertSame(
      1,
      preg_match(
        '/<a\b(?=[^>]*href="\/doctor\/agenda\?date='.$quotedDate.'[^"]*")[^>]*>[\s\S]*?<span class="availability-dot availability-dot--'.$quotedState.'"><\/span>[\s\S]*?<\/a>/',
        $content,
      ),
      "Expected {$date->toDateString()} to render an {$state} availability dot.",
    );
  }

  private function selectByName(string $content, string $name): string
  {
    $quotedName = preg_quote($name, '/');
    $this->assertSame(1, preg_match('/<select\b[^>]*name="'.$quotedName.'"[^>]*>[\s\S]*?<\/select>/', $content, $matches));

    return $matches[0];
  }

  private function selectById(string $content, string $id): string
  {
    $quotedId = preg_quote($id, '/');
    $this->assertSame(1, preg_match('/<select\b[^>]*id="'.$quotedId.'"[^>]*>[\s\S]*?<\/select>/', $content, $matches));

    return $matches[0];
  }
}
