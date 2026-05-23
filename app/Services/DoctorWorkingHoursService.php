<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\WorkingHour;
use App\Support\ScheduleTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorWorkingHoursService
{
  private const WEEKDAYS = [
    1 => 'Lunedi',
    2 => 'Martedi',
    3 => 'Mercoledi',
    4 => 'Giovedi',
    5 => 'Venerdi',
    6 => 'Sabato',
    7 => 'Domenica',
  ];

  public function __construct(private readonly ScheduleAppointmentImpactService $appointments)
  {
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

        $rows[] = ['start_time' => '', 'end_time' => ''];

        return [$weekday => [
          'label' => $label,
          'rows' => $rows,
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

  private function normalizeWorkingHours(array $input): array
  {
    $windows = [];

    foreach (self::WEEKDAYS as $weekday => $label) {
      $rows = $input[$weekday] ?? $input[(string) $weekday] ?? [];
      $dayWindows = [];

      foreach ($rows as $row) {
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

        ScheduleTime::assertRange($start, $end, 'working_hours', "{$label}: la fine deve essere successiva all'inizio.");

        $dayWindows[] = [
          'start_time' => $start,
          'end_time' => $end,
        ];
      }

      usort($dayWindows, fn (array $a, array $b): int => ScheduleTime::toMinutes($a['start_time']) <=> ScheduleTime::toMinutes($b['start_time']));
      $this->rejectOverlappingWindows($dayWindows, $label);

      if ($dayWindows !== []) {
        $windows[$weekday] = $dayWindows;
      }
    }

    return $windows;
  }

  private function rejectOverlappingWindows(array $dayWindows, string $label): void
  {
    for ($i = 1; $i < count($dayWindows); $i++) {
      if (ScheduleTime::toMinutes($dayWindows[$i]['start_time']) < ScheduleTime::toMinutes($dayWindows[$i - 1]['end_time'])) {
        throw ValidationException::withMessages([
          'working_hours' => "{$label}: le fasce orarie non possono sovrapporsi.",
        ]);
      }
    }
  }
}
