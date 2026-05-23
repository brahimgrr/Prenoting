<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorClosureService
{
  public function __construct(private readonly ScheduleAppointmentImpactService $appointments)
  {
  }

  public function createClosure(DoctorProfile $doctor, array $input, bool $confirmed = false): Collection
  {
    $closures = $this->normalizeClosureInput($input, allowRange: true);
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

  public function updateClosure(DoctorProfile $doctor, ScheduleClosure $closure, array $input, bool $confirmed = false): void
  {
    abort_unless($doctor->id === $closure->doctor_profile_id, 404);

    $closures = $this->normalizeClosureInput($input, allowRange: false);
    $normalized = $closures[0];

    DB::transaction(function () use ($doctor, $closure, $closures, $normalized, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->closureConflicts($doctor, $closures),
        ScheduleAppointmentImpactService::CLOSURE_CANCELLATION_REASON,
        $confirmed,
      );

      $closure->forceFill([
        'date' => $normalized['date'],
        'start_time' => $normalized['start_time'],
        'end_time' => $normalized['end_time'],
        'reason' => $normalized['reason'],
      ])->save();
    });
  }

  public function deleteClosure(DoctorProfile $doctor, ScheduleClosure $closure, bool $confirmed = false): void
  {
    abort_unless($doctor->id === $closure->doctor_profile_id, 404);

    DB::transaction(function () use ($doctor, $closure, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->closureModelConflicts($doctor, $closure),
        ScheduleAppointmentImpactService::CLOSURE_CANCELLATION_REASON,
        $confirmed,
      );

      $closure->delete();
    });
  }

  private function normalizeClosureInput(array $input, bool $allowRange): array
  {
    $startDate = CarbonImmutable::parse((string) $input['date'])->startOfDay();
    $endDate = CarbonImmutable::parse((string) ($input['end_date'] ?? $input['date']))->startOfDay();
    $allDay = ($input['all_day'] ?? null) === '1';

    if ($endDate->lessThan($startDate)) {
      throw ValidationException::withMessages(['closure' => 'La data finale deve essere successiva alla data iniziale.']);
    }

    if (! $allowRange && ! $endDate->equalTo($startDate)) {
      throw ValidationException::withMessages(['closure' => 'La modifica di una chiusura riguarda una sola data.']);
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
      if (CarbonImmutable::parse($closure['date'])->startOfDay()->lessThan(CarbonImmutable::now()->startOfDay())) {
        throw ValidationException::withMessages(['closure' => 'Non puoi creare chiusure in date passate.']);
      }
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
      if ($this->containsClosureWindow($this->closureModelWindow($existingClosure), $newWindow)) {
        throw ValidationException::withMessages([
          'closure' => 'Questa chiusura e gia coperta da una chiusura esistente.',
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
}
