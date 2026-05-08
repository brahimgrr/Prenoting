<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleService
{
  public const WEEKDAYS = [
    1 => 'Lunedi',
    2 => 'Martedi',
    3 => 'Mercoledi',
    4 => 'Giovedi',
    5 => 'Venerdi',
    6 => 'Sabato',
    7 => 'Domenica',
  ];

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

        $rows[] = ['start_time' => '', 'end_time' => ''];

        return [$weekday => [
          'label' => $label,
          'rows' => $rows,
        ]];
      })
      ->all();
  }

  public function replaceWeeklyTemplate(DoctorProfile $doctor, array $input): void
  {
    $windows = $this->normalizeWorkingHours($input);
    $conflicts = $this->appointmentsUnsupportedBy($doctor, $windows);

    if ($conflicts->isNotEmpty()) {
      throw ValidationException::withMessages([
        'working_hours' => 'Gli orari escludono appuntamenti attivi: '.$this->formatAppointmentConflicts($conflicts),
      ]);
    }

    DB::transaction(function () use ($doctor, $windows): void {
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

  public function createClosure(DoctorProfile $doctor, array $input): Collection
  {
    $closures = $this->normalizeClosureInput($input, allowRange: true);
    $this->rejectClosureAppointmentConflicts($doctor, $closures);

    return DB::transaction(fn (): Collection => collect($closures)
      ->map(fn (array $closure): ScheduleClosure => ScheduleClosure::create([
        'doctor_profile_id' => $doctor->id,
        'date' => $closure['date'],
        'start_time' => $closure['start_time'],
        'end_time' => $closure['end_time'],
        'reason' => $closure['reason'],
      ])));
  }

  public function updateClosure(DoctorProfile $doctor, ScheduleClosure $closure, array $input): void
  {
    $this->assertOwns($doctor, $closure->doctor_profile_id);
    $closures = $this->normalizeClosureInput($input, allowRange: false);
    $this->rejectClosureAppointmentConflicts($doctor, $closures);
    $normalized = $closures[0];

    $closure->forceFill([
      'date' => $normalized['date'],
      'start_time' => $normalized['start_time'],
      'end_time' => $normalized['end_time'],
      'reason' => $normalized['reason'],
    ])->save();
  }

  public function deleteClosure(DoctorProfile $doctor, ScheduleClosure $closure): void
  {
    $this->assertOwns($doctor, $closure->doctor_profile_id);
    $closure->delete();
  }

  public function createSpecialOpening(DoctorProfile $doctor, array $input): SpecialOpening
  {
    $opening = $this->normalizeSpecialOpeningInput($input);
    $this->rejectRedundantSpecialOpening($doctor, $opening);

    return SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $opening['date'],
      'start_time' => $opening['start_time'],
      'end_time' => $opening['end_time'],
      'note' => $opening['note'],
    ]);
  }

  public function updateSpecialOpening(DoctorProfile $doctor, SpecialOpening $specialOpening, array $input): void
  {
    $this->assertOwns($doctor, $specialOpening->doctor_profile_id);
    $opening = $this->normalizeSpecialOpeningInput($input);
    $this->rejectRedundantSpecialOpening($doctor, $opening, $specialOpening);
    $conflicts = $this->appointmentsUnsupportedBy($doctor, null, $specialOpening, $opening);

    if ($conflicts->isNotEmpty()) {
      throw ValidationException::withMessages([
        'special_opening' => 'Questa modifica escluderebbe appuntamenti attivi: '.$this->formatAppointmentConflicts($conflicts),
      ]);
    }

    $specialOpening->forceFill($opening)->save();
  }

  public function deleteSpecialOpening(DoctorProfile $doctor, SpecialOpening $specialOpening): void
  {
    $this->assertOwns($doctor, $specialOpening->doctor_profile_id);
    $conflicts = $this->appointmentsUnsupportedBy($doctor, null, $specialOpening);

    if ($conflicts->isNotEmpty()) {
      throw ValidationException::withMessages([
        'special_opening' => 'Questa apertura supporta appuntamenti attivi: '.$this->formatAppointmentConflicts($conflicts),
      ]);
    }

    $specialOpening->delete();
  }

  public function upcomingEvents(DoctorProfile $doctor, CarbonImmutable $from, int $limit = 8): Collection
  {
    $closures = $doctor->closures()
      ->whereDate('date', '>=', $from->toDateString())
      ->orderBy('date')
      ->orderBy('start_time')
      ->limit($limit)
      ->get()
      ->map(fn (ScheduleClosure $closure): array => [
        'type' => 'closure',
        'date' => CarbonImmutable::parse($closure->date),
        'start_time' => $closure->start_time ? substr((string) $closure->start_time, 0, 5) : null,
        'end_time' => $closure->end_time ? substr((string) $closure->end_time, 0, 5) : null,
        'title' => $closure->reason ?: 'Chiusura',
        'model' => $closure,
      ]);

    $specialOpenings = $doctor->specialOpenings()
      ->whereDate('date', '>=', $from->toDateString())
      ->orderBy('date')
      ->orderBy('start_time')
      ->limit($limit)
      ->get()
      ->map(fn (SpecialOpening $opening): array => [
        'type' => 'special_opening',
        'date' => CarbonImmutable::parse($opening->date),
        'start_time' => substr((string) $opening->start_time, 0, 5),
        'end_time' => substr((string) $opening->end_time, 0, 5),
        'title' => $opening->note ?: 'Apertura extra',
        'model' => $opening,
      ]);

    return $closures
      ->concat($specialOpenings)
      ->sortBy([
        fn (array $event): int => $event['date']->getTimestamp(),
        fn (array $event): string => $event['start_time'] ?? '00:00',
      ])
      ->take($limit)
      ->values();
  }

  public function eventsForDate(DoctorProfile $doctor, CarbonImmutable $date): Collection
  {
    return $this->upcomingEvents($doctor, $date, 50)
      ->filter(fn (array $event): bool => $event['date']->toDateString() === $date->toDateString())
      ->values();
  }

  private function normalizeWorkingHours(array $input): array
  {
    $windows = [];

    foreach (self::WEEKDAYS as $weekday => $label) {
      $rows = $input[$weekday] ?? $input[(string) $weekday] ?? [];
      $dayWindows = [];

      foreach ($rows as $index => $row) {
        $start = trim((string) ($row['start_time'] ?? ''));
        $end = trim((string) ($row['end_time'] ?? ''));

        if ($start === '' && $end === '') {
          continue;
        }

        if ($start === '' || $end === '') {
          throw ValidationException::withMessages([
            'working_hours' => "{$label}: compila sia inizio sia fine.",
          ]);
        }

        $this->assertGridTime($start, 'working_hours');
        $this->assertGridTime($end, 'working_hours');

        if ($this->timeToMinutes($end) <= $this->timeToMinutes($start)) {
          throw ValidationException::withMessages([
            'working_hours' => "{$label}: la fine deve essere successiva all'inizio.",
          ]);
        }

        $dayWindows[] = [
          'start_time' => $start,
          'end_time' => $end,
        ];
      }

      usort($dayWindows, fn (array $a, array $b): int => $this->timeToMinutes($a['start_time']) <=> $this->timeToMinutes($b['start_time']));

      for ($i = 1; $i < count($dayWindows); $i++) {
        if ($this->timeToMinutes($dayWindows[$i]['start_time']) < $this->timeToMinutes($dayWindows[$i - 1]['end_time'])) {
          throw ValidationException::withMessages([
            'working_hours' => "{$label}: le fasce orarie non possono sovrapporsi.",
          ]);
        }
      }

      if ($dayWindows !== []) {
        $windows[$weekday] = $dayWindows;
      }
    }

    return $windows;
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
    $closures = [];

    if ($allDay) {
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

    $startTime = trim((string) ($input['start_time'] ?? ''));
    $endTime = trim((string) ($input['end_time'] ?? ''));

    if ($startTime === '' || $endTime === '') {
      throw ValidationException::withMessages(['closure' => 'Indica inizio e fine della chiusura parziale.']);
    }

    $this->assertGridTime($startTime, 'closure');
    $this->assertGridTime($endTime, 'closure');

    if ($this->timeToMinutes($endTime) <= $this->timeToMinutes($startTime)) {
      throw ValidationException::withMessages(['closure' => 'La fine della chiusura deve essere successiva all\'inizio.']);
    }

    return [[
      'date' => $startDate->toDateString(),
      'start_time' => $startTime,
      'end_time' => $endTime,
      'reason' => $reason,
    ]];
  }

  private function normalizeSpecialOpeningInput(array $input): array
  {
    $date = CarbonImmutable::parse((string) $input['date'])->startOfDay();
    $startTime = trim((string) ($input['start_time'] ?? ''));
    $endTime = trim((string) ($input['end_time'] ?? ''));

    $this->assertGridTime($startTime, 'special_opening');
    $this->assertGridTime($endTime, 'special_opening');

    if ($this->timeToMinutes($endTime) <= $this->timeToMinutes($startTime)) {
      throw ValidationException::withMessages(['special_opening' => 'La fine deve essere successiva all\'inizio.']);
    }

    return [
      'date' => $date->toDateString(),
      'start_time' => $startTime,
      'end_time' => $endTime,
      'note' => trim((string) ($input['note'] ?? '')) ?: null,
    ];
  }

  private function rejectClosureAppointmentConflicts(DoctorProfile $doctor, array $closures): void
  {
    $conflicts = collect();

    foreach ($closures as $closure) {
      $date = CarbonImmutable::parse($closure['date'])->startOfDay();
      $start = $closure['start_time']
        ? $this->combineDateAndTime($date, $closure['start_time'])
        : $date->startOfDay();
      $end = $closure['end_time']
        ? $this->combineDateAndTime($date, $closure['end_time'])
        : $date->endOfDay();

      $appointments = Appointment::withPortalRelations()
        ->where('doctor_profile_id', $doctor->id)
        ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
        ->where('start_at', '>=', CarbonImmutable::now())
        ->where('start_at', '<', $end)
        ->where('end_at', '>', $start)
        ->orderBy('start_at')
        ->get();

      $conflicts = $conflicts->concat($appointments);
    }

    if ($conflicts->isNotEmpty()) {
      throw ValidationException::withMessages([
        'closure' => 'La chiusura si sovrappone ad appuntamenti attivi: '.$this->formatAppointmentConflicts($conflicts),
      ]);
    }
  }

  private function rejectRedundantSpecialOpening(
    DoctorProfile $doctor,
    array $opening,
    ?SpecialOpening $excluding = null,
  ): void {
    $date = CarbonImmutable::parse($opening['date'])->startOfDay();
    $start = $this->combineDateAndTime($date, $opening['start_time']);
    $end = $this->combineDateAndTime($date, $opening['end_time']);

    foreach ($this->openingWindowsForDate($doctor, $date, $excluding) as $window) {
      if ($window['start_at']->lessThanOrEqualTo($start) && $window['end_at']->greaterThanOrEqualTo($end)) {
        throw ValidationException::withMessages([
          'special_opening' => 'Questa apertura e gia coperta dagli orari esistenti.',
        ]);
      }
    }
  }

  private function appointmentsUnsupportedBy(
    DoctorProfile $doctor,
    ?array $weeklyWindows,
    ?SpecialOpening $excludingSpecialOpening = null,
    ?array $replacementSpecialOpening = null,
  ): Collection {
    $appointments = Appointment::withPortalRelations()
      ->where('doctor_profile_id', $doctor->id)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
      ->where('start_at', '>=', CarbonImmutable::now())
      ->orderBy('start_at')
      ->get();

    return $appointments
      ->filter(fn (Appointment $appointment): bool => ! $this->appointmentCovered(
        $doctor,
        $appointment,
        $weeklyWindows,
        $excludingSpecialOpening,
        $replacementSpecialOpening,
      ))
      ->values();
  }

  private function appointmentCovered(
    DoctorProfile $doctor,
    Appointment $appointment,
    ?array $weeklyWindows,
    ?SpecialOpening $excludingSpecialOpening,
    ?array $replacementSpecialOpening,
  ): bool {
    $start = CarbonImmutable::parse($appointment->start_at);
    $end = CarbonImmutable::parse($appointment->end_at);
    $date = $start->startOfDay();
    $windows = $weeklyWindows === null
      ? $this->openingWindowsForDate($doctor, $date, $excludingSpecialOpening)
      : collect($weeklyWindows[$date->dayOfWeekIso] ?? [])
        ->map(fn (array $window): array => [
          'start_at' => $this->combineDateAndTime($date, $window['start_time']),
          'end_at' => $this->combineDateAndTime($date, $window['end_time']),
        ])
        ->concat($this->specialOpeningWindowsForDate($doctor, $date, $excludingSpecialOpening));

    if (
      $replacementSpecialOpening &&
      CarbonImmutable::parse($replacementSpecialOpening['date'])->toDateString() === $date->toDateString()
    ) {
      $windows->push([
        'start_at' => $this->combineDateAndTime($date, $replacementSpecialOpening['start_time']),
        'end_at' => $this->combineDateAndTime($date, $replacementSpecialOpening['end_time']),
      ]);
    }

    return $windows->contains(
      fn (array $window): bool => $window['start_at']->lessThanOrEqualTo($start) && $window['end_at']->greaterThanOrEqualTo($end)
    );
  }

  private function openingWindowsForDate(
    DoctorProfile $doctor,
    CarbonImmutable $date,
    ?SpecialOpening $excludingSpecialOpening = null,
  ): Collection {
    $workingHours = $doctor->workingHours()
      ->where('is_active', true)
      ->where('weekday', $date->dayOfWeekIso)
      ->where(function ($query) use ($date): void {
        $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date->toDateString());
      })
      ->where(function ($query) use ($date): void {
        $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date->toDateString());
      })
      ->get()
      ->map(fn (WorkingHour $window): array => [
        'start_at' => $this->combineDateAndTime($date, $window->start_time),
        'end_at' => $this->combineDateAndTime($date, $window->end_time),
      ]);

    return $workingHours->concat($this->specialOpeningWindowsForDate($doctor, $date, $excludingSpecialOpening));
  }

  private function specialOpeningWindowsForDate(
    DoctorProfile $doctor,
    CarbonImmutable $date,
    ?SpecialOpening $excludingSpecialOpening = null,
  ): Collection {
    return $doctor->specialOpenings()
      ->whereDate('date', $date->toDateString())
      ->when($excludingSpecialOpening, fn ($query) => $query->whereKeyNot($excludingSpecialOpening->id))
      ->get()
      ->map(fn (SpecialOpening $window): array => [
        'start_at' => $this->combineDateAndTime($date, $window->start_time),
        'end_at' => $this->combineDateAndTime($date, $window->end_time),
      ]);
  }

  private function assertGridTime(string $time, string $field): void
  {
    if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
      throw ValidationException::withMessages([$field => 'Usa un orario valido nel formato HH:MM.']);
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));
    if ($hour < 0 || $hour > 23 || ! in_array($minute, [0, 30], true)) {
      throw ValidationException::withMessages([$field => 'Gli orari devono rispettare la griglia di 30 minuti.']);
    }
  }

  private function timeToMinutes(string $time): int
  {
    [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

    return $hour * 60 + $minute;
  }

  private function combineDateAndTime(CarbonImmutable $date, string $time): CarbonImmutable
  {
    [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

    return $date->setTime($hour, $minute);
  }

  private function formatAppointmentConflicts(Collection $appointments): string
  {
    return $appointments
      ->unique('id')
      ->take(4)
      ->map(fn (Appointment $appointment): string => CarbonImmutable::parse($appointment->start_at)->format('d/m/Y H:i').' '.$appointment->patientName())
      ->implode(', ');
  }

  private function assertOwns(DoctorProfile $doctor, int $doctorProfileId): void
  {
    abort_unless($doctor->id === $doctorProfileId, 404);
  }
}
