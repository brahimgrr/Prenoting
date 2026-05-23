<?php

namespace App\Services;

use App\Exceptions\ScheduleAppointmentConflictsException;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleAppointmentImpactService
{
  public const WORKING_HOURS_CANCELLATION_REASON = 'Cambio orario lavoro medico';
  public const CLOSURE_CANCELLATION_REASON = 'Chiusura straordinaria studio';

  public function __construct(private readonly ScheduleWindowService $windows)
  {
  }

  public function cancelOrRequestConfirmation(Collection $appointments, string $reason, bool $confirmed): void
  {
    $appointments = $appointments->unique('id')->values();

    if ($appointments->isEmpty()) {
      return;
    }

    if (! $confirmed) {
      throw new ScheduleAppointmentConflictsException($appointments, $reason);
    }

    $this->cancelAppointments($appointments, $reason);
  }

  public function closureConflicts(DoctorProfile $doctor, array $closures): Collection
  {
    $conflicts = collect();

    foreach ($closures as $closure) {
      $window = $this->closureDateTimeWindow($closure['date'], $closure['start_time'], $closure['end_time']);
      $conflicts = $conflicts->concat($this->appointmentsOverlapping($doctor, $window['start_at'], $window['end_at']));
    }

    return $conflicts->unique('id')->values();
  }

  public function closureModelConflicts(DoctorProfile $doctor, ScheduleClosure $closure): Collection
  {
    $window = $this->closureDateTimeWindow(
      CarbonImmutable::parse($closure->date)->toDateString(),
      $closure->start_time ? (string) $closure->start_time : null,
      $closure->end_time ? (string) $closure->end_time : null,
    );

    return $this->appointmentsOverlapping($doctor, $window['start_at'], $window['end_at']);
  }

  public function appointmentsUnsupportedBy(
    DoctorProfile $doctor,
    ?array $weeklyWindows,
    ?SpecialOpening $excludingSpecialOpening = null,
    ?array $replacementSpecialOpening = null,
  ): Collection {
    return Appointment::withPortalRelations()
      ->where('doctor_profile_id', $doctor->id)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
      ->where('start_at', '>=', CarbonImmutable::now())
      ->orderBy('start_at')
      ->get()
      ->filter(fn (Appointment $appointment): bool => ! $this->appointmentCovered(
        $doctor,
        $appointment,
        $weeklyWindows,
        $excludingSpecialOpening,
        $replacementSpecialOpening,
      ))
      ->values();
  }

  private function appointmentsOverlapping(DoctorProfile $doctor, CarbonImmutable $start, CarbonImmutable $end): Collection
  {
    return Appointment::withPortalRelations()
      ->where('doctor_profile_id', $doctor->id)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
      ->where('start_at', '>=', CarbonImmutable::now())
      ->where('start_at', '<', $end)
      ->where('end_at', '>', $start)
      ->orderBy('start_at')
      ->get();
  }

  private function cancelAppointments(Collection $appointments, string $reason): void
  {
    $appointmentIds = $appointments->pluck('id')->all();

    if ($appointmentIds === []) {
      return;
    }

    Appointment::query()
      ->whereKey($appointmentIds)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
      ->where('start_at', '>=', CarbonImmutable::now())
      ->update([
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => $reason,
        'cancelled_by_role' => Appointment::CANCELLED_BY_DOCTOR,
        'cancelled_at' => now(),
        'updated_at' => now(),
      ]);
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
      ? $this->windows->openingWindowsForDate($doctor, $date, $excludingSpecialOpening)
      : $this->windows->openingWindowsFromTemplate($doctor, $date, $weeklyWindows, $excludingSpecialOpening);

    if (
      $replacementSpecialOpening &&
      CarbonImmutable::parse($replacementSpecialOpening['date'])->toDateString() === $date->toDateString()
    ) {
      $windows->push([
        'start_at' => ScheduleTime::combine($date, $replacementSpecialOpening['start_time']),
        'end_at' => ScheduleTime::combine($date, $replacementSpecialOpening['end_time']),
      ]);
    }

    return $windows->contains(
      fn (array $window): bool => $window['start_at']->lessThanOrEqualTo($start) && $window['end_at']->greaterThanOrEqualTo($end)
    );
  }

  private function closureDateTimeWindow(string $date, ?string $startTime, ?string $endTime): array
  {
    $day = CarbonImmutable::parse($date)->startOfDay();

    return [
      'start_at' => $startTime ? ScheduleTime::combine($day, $startTime) : $day->startOfDay(),
      'end_at' => $endTime ? ScheduleTime::combine($day, $endTime) : $day->endOfDay(),
    ];
  }
}
