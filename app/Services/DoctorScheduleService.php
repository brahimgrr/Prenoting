<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Models\WorkingHour;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleService
{
  public const CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD = 'confirm_appointment_cancellations';

  private const WEEKDAYS = [
    1 => 'Lunedi',
    2 => 'Martedi',
    3 => 'Mercoledi',
    4 => 'Giovedi',
    5 => 'Venerdi',
    6 => 'Sabato',
    7 => 'Domenica',
  ];

  public function __construct(
    private readonly ScheduleWindowService $windows,
    private readonly ScheduleAppointmentImpactService $appointments,
  ) {
  }

  public function weeklyTemplateForForm(DoctorProfile $doctor): array
  {
    $rowsByDay = $doctor->workingHours()
      ->where('is_active', true)
      ->whereNull('effective_from')
      ->whereNull('effective_until')
      ->orderBy('weekday')
      ->orderBy('start_time')
      ->get()
      ->groupBy('weekday');

    return collect(self::WEEKDAYS)
      ->mapWithKeys(function (string $label, int $weekday) use ($rowsByDay): array {
        $rows = $rowsByDay->get($weekday, collect())
          ->map(fn (WorkingHour $workingHour): array => [
            'start_time' => substr((string) $workingHour->start_time, 0, 5),
            'end_time' => substr((string) $workingHour->end_time, 0, 5),
          ])
          ->values()
          ->all();

        $fields = [
          'open_time' => '',
          'close_time' => '',
          'break_start_time' => '',
          'break_end_time' => '',
        ];

        if (count($rows) === 1) {
          $fields['open_time'] = $rows[0]['start_time'];
          $fields['close_time'] = $rows[0]['end_time'];
        } elseif (count($rows) >= 2) {
          $fields['open_time'] = $rows[0]['start_time'];
          $fields['break_start_time'] = $rows[0]['end_time'];
          $fields['break_end_time'] = $rows[1]['start_time'];
          $fields['close_time'] = $rows[1]['end_time'];
        }

        return [$weekday => [
          'label' => $label,
          'fields' => $fields,
        ]];
      })
      ->all();
  }

  public function replaceWeeklyTemplate(DoctorProfile $doctor, array $input, bool $confirmed = false): void
  {
    $windows = $this->normalizeWorkingHours($input);

    DB::transaction(function () use ($doctor, $windows, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->appointmentsUnsupportedBy($doctor, $windows),
        ScheduleAppointmentImpactService::WORKING_HOURS_CANCELLATION_REASON,
        $confirmed,
      );

      $doctor->workingHours()->delete();

      foreach ($windows as $weekday => $dayWindows) {
        foreach ($dayWindows as $window) {
          WorkingHour::create([
            'doctor_profile_id' => $doctor->id,
            'weekday' => $weekday,
            'start_time' => $window['start_time'],
            'end_time' => $window['end_time'],
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => true,
          ]);
        }
      }
    });
  }

  public function createClosure(DoctorProfile $doctor, array $input, bool $confirmed = false): Collection
  {
    $closures = $this->normalizeClosureInput($input);
    $this->rejectPastClosureDates($closures);

    return DB::transaction(function () use ($doctor, $closures, $confirmed): Collection {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->closureConflicts($doctor, $closures),
        ScheduleAppointmentImpactService::CLOSURE_CANCELLATION_REASON,
        $confirmed,
      );

      return collect($closures)->map(function (array $closure) use ($doctor): ScheduleClosure {
        $this->rejectCoveredClosureAndDeleteContained($doctor, $closure);

        return ScheduleClosure::create([
          'doctor_profile_id' => $doctor->id,
          'date' => $closure['date'],
          'start_time' => $closure['start_time'],
          'end_time' => $closure['end_time'],
          'reason' => $closure['reason'],
        ]);
      });
    });
  }

  public function deleteClosure(DoctorProfile $doctor, ScheduleClosure $closure, bool $confirmed = false): void
  {
    $this->authorizeOwner($doctor, $closure);

    DB::transaction(function () use ($doctor, $closure, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->closureModelConflicts($doctor, $closure),
        ScheduleAppointmentImpactService::CLOSURE_CANCELLATION_REASON,
        $confirmed,
      );

      $closure->delete();
    });
  }

  public function createSpecialOpening(DoctorProfile $doctor, array $input): SpecialOpening
  {
    $opening = $this->normalizeSpecialOpeningInput($input);
    $this->rejectPastDate($opening['date'], 'special_opening', 'Non puoi creare aperture extra in date passate.');
    $this->rejectOverlappingSpecialOpening($doctor, $opening);
    $this->rejectRedundantSpecialOpening($doctor, $opening);

    return SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $opening['date'],
      'start_time' => $opening['start_time'],
      'end_time' => $opening['end_time'],
      'note' => $opening['note'],
    ]);
  }

  public function deleteSpecialOpening(DoctorProfile $doctor, SpecialOpening $specialOpening, bool $confirmed = false): void
  {
    $this->authorizeOwner($doctor, $specialOpening);

    DB::transaction(function () use ($doctor, $specialOpening, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->appointmentsUnsupportedBy($doctor, null, $specialOpening),
        ScheduleAppointmentImpactService::WORKING_HOURS_CANCELLATION_REASON,
        $confirmed,
      );

      $specialOpening->delete();
    });
  }

  private function normalizeWorkingHours(array $input): array
  {
    $windows = [];

    foreach (self::WEEKDAYS as $weekday => $label) {
      $row = $input[$weekday] ?? $input[(string) $weekday] ?? [];
      $open = trim((string) ($row['open_time'] ?? ''));
      $close = trim((string) ($row['close_time'] ?? ''));
      $breakStart = trim((string) ($row['break_start_time'] ?? ''));
      $breakEnd = trim((string) ($row['break_end_time'] ?? ''));

      if ($open === '' && $close === '' && $breakStart === '' && $breakEnd === '') {
        continue;
      }

      if ($open === '' || $close === '') {
        throw ValidationException::withMessages([
          'working_hours' => "{$label}: compila sia apertura sia chiusura.",
        ]);
      }

      ScheduleTime::assertRange($open, $close, 'working_hours', "{$label}: la chiusura deve essere successiva all'apertura.");

      $hasBreakStart = $breakStart !== '';
      $hasBreakEnd = $breakEnd !== '';

      if ($hasBreakStart xor $hasBreakEnd) {
        throw ValidationException::withMessages([
          'working_hours' => "{$label}: compila sia inizio sia fine pausa pranzo.",
        ]);
      }

      $dayWindows = [];

      if ($hasBreakStart && $hasBreakEnd) {
        ScheduleTime::assertRange($breakStart, $breakEnd, 'working_hours', "{$label}: la fine pausa pranzo deve essere successiva all'inizio.");

        if (
          ScheduleTime::toMinutes($breakStart) <= ScheduleTime::toMinutes($open)
          || ScheduleTime::toMinutes($breakEnd) >= ScheduleTime::toMinutes($close)
        ) {
          throw ValidationException::withMessages([
            'working_hours' => "{$label}: la pausa pranzo deve essere compresa negli orari di apertura.",
          ]);
        }

        $dayWindows[] = ['start_time' => $open, 'end_time' => $breakStart];
        $dayWindows[] = ['start_time' => $breakEnd, 'end_time' => $close];
      } else {
        $dayWindows[] = ['start_time' => $open, 'end_time' => $close];
      }

      if ($dayWindows !== []) {
        $windows[$weekday] = $dayWindows;
      }
    }

    return $windows;
  }

  private function normalizeClosureInput(array $input): array
  {
    $startDate = CarbonImmutable::parse((string) $input['date'])->startOfDay();
    $endDate = CarbonImmutable::parse((string) ($input['end_date'] ?? $input['date']))->startOfDay();
    $allDay = ($input['all_day'] ?? null) === '1';

    if ($endDate->lessThan($startDate)) {
      throw ValidationException::withMessages(['closure' => 'La data finale deve essere successiva alla data iniziale.']);
    }

    if (! $allDay && ! $endDate->equalTo($startDate)) {
      throw ValidationException::withMessages(['closure' => 'Le chiusure parziali devono essere su una sola giornata.']);
    }

    $reason = trim((string) ($input['reason'] ?? '')) ?: null;

    if ($allDay) {
      return $this->allDayClosures($startDate, $endDate, $reason);
    }

    $startTime = trim((string) ($input['start_time'] ?? ''));
    $endTime = trim((string) ($input['end_time'] ?? ''));

    if ($startTime === '' || $endTime === '') {
      throw ValidationException::withMessages(['closure' => 'Indica inizio e fine della chiusura parziale.']);
    }

    ScheduleTime::assertRange($startTime, $endTime, 'closure', 'La fine della chiusura deve essere successiva all\'inizio.');

    return [[
      'date' => $startDate->toDateString(),
      'start_time' => $startTime,
      'end_time' => $endTime,
      'reason' => $reason,
    ]];
  }

  private function allDayClosures(CarbonImmutable $startDate, CarbonImmutable $endDate, ?string $reason): array
  {
    $closures = [];

    for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
      $closures[] = [
        'date' => $date->toDateString(),
        'start_time' => null,
        'end_time' => null,
        'reason' => $reason,
      ];
    }

    return $closures;
  }

  private function rejectPastClosureDates(array $closures): void
  {
    foreach ($closures as $closure) {
      $this->rejectPastDate($closure['date'], 'closure', 'Non puoi creare chiusure in date passate.');
    }
  }

  private function rejectCoveredClosureAndDeleteContained(DoctorProfile $doctor, array $closure): void
  {
    $newWindow = $this->closureWindow($closure['start_time'], $closure['end_time']);
    $existingClosures = $doctor->closures()
      ->whereDate('date', $closure['date'])
      ->lockForUpdate()
      ->get();

    foreach ($existingClosures as $existingClosure) {
      $existingWindow = $this->closureModelWindow($existingClosure);

      if ($this->containsClosureWindow($existingWindow, $newWindow)) {
        throw ValidationException::withMessages([
          'closure' => 'Questa chiusura e gia coperta da una chiusura esistente.',
        ]);
      }

      if (
        $this->windowsOverlap($existingWindow, $newWindow)
        && ! $this->containsClosureWindow($newWindow, $existingWindow)
      ) {
        throw ValidationException::withMessages([
          'closure' => 'Questa chiusura si sovrappone a una chiusura esistente.',
        ]);
      }
    }

    $existingClosures
      ->filter(fn (ScheduleClosure $existingClosure): bool => $this->containsClosureWindow(
        $newWindow,
        $this->closureModelWindow($existingClosure),
      ))
      ->each(fn (ScheduleClosure $existingClosure): ?bool => $existingClosure->delete());
  }

  private function closureModelWindow(ScheduleClosure $closure): array
  {
    return $this->closureWindow(
      $closure->start_time ? (string) $closure->start_time : null,
      $closure->end_time ? (string) $closure->end_time : null,
    );
  }

  private function closureWindow(?string $startTime, ?string $endTime): array
  {
    return [
      'start' => $startTime === null ? 0 : ScheduleTime::toMinutes($startTime),
      'end' => $endTime === null ? 24 * 60 : ScheduleTime::toMinutes($endTime),
    ];
  }

  private function containsClosureWindow(array $outer, array $inner): bool
  {
    return $outer['start'] <= $inner['start'] && $outer['end'] >= $inner['end'];
  }

  private function windowsOverlap(array $first, array $second): bool
  {
    return $first['start'] < $second['end'] && $first['end'] > $second['start'];
  }

  private function normalizeSpecialOpeningInput(array $input): array
  {
    $date = CarbonImmutable::parse((string) $input['date'])->startOfDay();
    $startTime = trim((string) ($input['start_time'] ?? ''));
    $endTime = trim((string) ($input['end_time'] ?? ''));

    ScheduleTime::assertRange($startTime, $endTime, 'special_opening', 'La fine deve essere successiva all\'inizio.');

    return [
      'date' => $date->toDateString(),
      'start_time' => $startTime,
      'end_time' => $endTime,
      'note' => trim((string) ($input['note'] ?? '')) ?: null,
    ];
  }

  private function rejectPastDate(string $date, string $field, string $message): void
  {
    if (CarbonImmutable::parse($date)->startOfDay()->lessThan(CarbonImmutable::now()->startOfDay())) {
      throw ValidationException::withMessages([$field => $message]);
    }
  }

  private function rejectRedundantSpecialOpening(
    DoctorProfile $doctor,
    array $opening,
    ?SpecialOpening $excluding = null,
  ): void {
    $date = CarbonImmutable::parse($opening['date'])->startOfDay();
    $start = ScheduleTime::combine($date, $opening['start_time']);
    $end = ScheduleTime::combine($date, $opening['end_time']);

    foreach ($this->windows->openingWindowsForDate($doctor, $date, $excluding) as $window) {
      if ($window['start_at']->lessThanOrEqualTo($start) && $window['end_at']->greaterThanOrEqualTo($end)) {
        throw ValidationException::withMessages([
          'special_opening' => 'Questa apertura e gia coperta dagli orari esistenti.',
        ]);
      }
    }
  }

  private function rejectOverlappingSpecialOpening(
    DoctorProfile $doctor,
    array $opening,
    ?SpecialOpening $excluding = null,
  ): void {
    $date = CarbonImmutable::parse($opening['date'])->startOfDay();
    $newWindow = [
      'start' => ScheduleTime::toMinutes($opening['start_time']),
      'end' => ScheduleTime::toMinutes($opening['end_time']),
    ];

    $overlapsExisting = $doctor->specialOpenings()
      ->whereDate('date', $date->toDateString())
      ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->id))
      ->get()
      ->contains(function (SpecialOpening $existingOpening) use ($newWindow): bool {
        $existingWindow = [
          'start' => ScheduleTime::toMinutes((string) $existingOpening->start_time),
          'end' => ScheduleTime::toMinutes((string) $existingOpening->end_time),
        ];

        return $this->windowsOverlap($existingWindow, $newWindow);
      });

    if ($overlapsExisting) {
      throw ValidationException::withMessages([
        'special_opening' => 'Questa apertura extra si sovrappone a un\'apertura extra esistente.',
      ]);
    }
  }

  private function authorizeOwner(DoctorProfile $doctor, ScheduleClosure|SpecialOpening $model): void
  {
    abort_unless($doctor->id === $model->doctor_profile_id, 404);
  }
}
