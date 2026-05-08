<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\ScheduleClosure;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
  public const SLOT_STEP_MINUTES = 30;
  public const BOOKING_HORIZON_DAYS = 90;

  public function primaryDoctor(): DoctorProfile
  {
    return DoctorProfile::query()
      ->where('is_active', true)
      ->orderBy('id')
      ->firstOrFail();
  }

  public function availableSlotsForDate(
    DoctorProfile $doctor,
    MedicalService $service,
    CarbonImmutable|string $date,
    ?Appointment $excludingAppointment = null,
    bool $includePast = false,
  ): Collection {
    return $this->generatedSlotsForDate(
      $doctor,
      $date,
      max(1, (int) $service->duration_minutes),
      $excludingAppointment,
      $includePast,
    );
  }

  public function agendaSlotsForDate(DoctorProfile $doctor, CarbonImmutable|string $date): Collection
  {
    return $this->generatedSlotsForDate($doctor, $date, self::SLOT_STEP_MINUTES, null, true);
  }

  public function availableDates(
    DoctorProfile $doctor,
    MedicalService $service,
    string $selectedPeriod = 'all',
    ?Appointment $excludingAppointment = null,
  ): Collection {
    $today = CarbonImmutable::now()->startOfDay();
    $end = $today->addDays(self::BOOKING_HORIZON_DAYS);
    $dates = collect();

    for ($date = $today; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
      $slots = $this->availableSlotsForDate($doctor, $service, $date, $excludingAppointment)
        ->filter(fn (VirtualAvailabilitySlot $slot): bool => $this->matchesPeriod($slot, $selectedPeriod));

      if ($slots->isNotEmpty()) {
        $dates->push($date);
      }
    }

    return $dates;
  }

  public function availableSlotByKey(
    DoctorProfile $doctor,
    MedicalService $service,
    string $slotKey,
    ?Appointment $excludingAppointment = null,
  ): ?VirtualAvailabilitySlot {
    $start = $this->parseSlotStart($slotKey);
    if (! $start) {
      return null;
    }

    return $this->availableSlotsForDate($doctor, $service, $start, $excludingAppointment)
      ->first(fn (VirtualAvailabilitySlot $slot): bool => $slot->key === $start->format('Y-m-d\TH:i'));
  }

  public function parseSlotStart(string $slotStart): ?CarbonImmutable
  {
    try {
      return CarbonImmutable::parse($slotStart)->second(0)->microsecond(0);
    } catch (\Throwable) {
      return null;
    }
  }

  public function closuresForDate(DoctorProfile $doctor, CarbonImmutable|string $date): Collection
  {
    $day = $this->normalizeDate($date);

    return ScheduleClosure::query()
      ->where('doctor_profile_id', $doctor->id)
      ->whereDate('date', $day->toDateString())
      ->orderBy('start_time')
      ->get()
      ->map(function (ScheduleClosure $closure) use ($day): array {
        return [
          'closure' => $closure,
          'start_at' => $closure->start_time
            ? $this->combineDateAndTime($day, $closure->start_time)
            : $day->startOfDay(),
          'end_at' => $closure->end_time
            ? $this->combineDateAndTime($day, $closure->end_time)
            : $day->endOfDay(),
        ];
      });
  }

  private function generatedSlotsForDate(
    DoctorProfile $doctor,
    CarbonImmutable|string $date,
    int $durationMinutes,
    ?Appointment $excludingAppointment = null,
    bool $includePast = false,
  ): Collection {
    $day = $this->normalizeDate($date);
    $windows = $this->openingWindowsForDate($doctor, $day);
    $closures = $this->closureIntervalsForDate($doctor, $day);
    $appointments = $this->activeAppointmentsForDate($doctor, $day, $excludingAppointment);
    $now = CarbonImmutable::now();
    $slots = collect();

    foreach ($windows as $window) {
      for (
        $start = $window['start_at'];
        $start->addMinutes($durationMinutes)->lessThanOrEqualTo($window['end_at']);
        $start = $start->addMinutes(self::SLOT_STEP_MINUTES)
      ) {
        $end = $start->addMinutes($durationMinutes);

        if (! $includePast && $start->lessThanOrEqualTo($now)) {
          continue;
        }

        if ($this->overlapsAny($start, $end, $closures) || $this->overlapsAny($start, $end, $appointments)) {
          continue;
        }

        $slots->put($start->format('Y-m-d\TH:i'), new VirtualAvailabilitySlot(
          $start->format('Y-m-d\TH:i'),
          $start,
          $end,
          $doctor->id,
        ));
      }
    }

    return $slots
      ->sortBy(fn (VirtualAvailabilitySlot $slot): int => $slot->start_at->getTimestamp())
      ->values();
  }

  private function openingWindowsForDate(DoctorProfile $doctor, CarbonImmutable $day): Collection
  {
    $workingHours = $doctor->workingHours()
      ->where('is_active', true)
      ->where('weekday', $day->dayOfWeekIso)
      ->where(function ($query) use ($day): void {
        $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day->toDateString());
      })
      ->where(function ($query) use ($day): void {
        $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $day->toDateString());
      })
      ->get()
      ->map(fn ($window): array => [
        'start_at' => $this->combineDateAndTime($day, $window->start_time),
        'end_at' => $this->combineDateAndTime($day, $window->end_time),
      ]);

    $specialOpenings = $doctor->specialOpenings()
      ->whereDate('date', $day->toDateString())
      ->get()
      ->map(fn ($window): array => [
        'start_at' => $this->combineDateAndTime($day, $window->start_time),
        'end_at' => $this->combineDateAndTime($day, $window->end_time),
      ]);

    return $workingHours
      ->concat($specialOpenings)
      ->filter(fn (array $window): bool => $window['end_at']->greaterThan($window['start_at']))
      ->sortBy(fn (array $window): int => $window['start_at']->getTimestamp())
      ->values();
  }

  private function closureIntervalsForDate(DoctorProfile $doctor, CarbonImmutable $day): Collection
  {
    return $this->closuresForDate($doctor, $day)
      ->map(fn (array $closure): array => [
        'start_at' => $closure['start_at'],
        'end_at' => $closure['end_at'],
      ]);
  }

  private function activeAppointmentsForDate(
    DoctorProfile $doctor,
    CarbonImmutable $day,
    ?Appointment $excludingAppointment,
  ): Collection {
    $query = Appointment::query()
      ->where('doctor_profile_id', $doctor->id)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
      ->where('start_at', '<', $day->endOfDay())
      ->where('end_at', '>', $day->startOfDay());

    if ($excludingAppointment) {
      $query->whereKeyNot($excludingAppointment->id);
    }

    return $query
      ->get(['id', 'start_at', 'end_at'])
      ->map(fn (Appointment $appointment): array => [
        'start_at' => CarbonImmutable::parse($appointment->start_at),
        'end_at' => CarbonImmutable::parse($appointment->end_at),
      ]);
  }

  private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, Collection $intervals): bool
  {
    return $intervals->contains(
      fn (array $interval): bool => $start->lessThan($interval['end_at']) && $end->greaterThan($interval['start_at'])
    );
  }

  private function matchesPeriod(VirtualAvailabilitySlot $slot, string $selectedPeriod): bool
  {
    return match ($selectedPeriod) {
      'mattina' => $slot->start_at->hour < 13,
      'pomeriggio' => $slot->start_at->hour >= 13,
      default => true,
    };
  }

  private function normalizeDate(CarbonImmutable|string $date): CarbonImmutable
  {
    return $date instanceof CarbonImmutable
      ? $date->startOfDay()
      : CarbonImmutable::parse($date)->startOfDay();
  }

  private function combineDateAndTime(CarbonImmutable $date, string $time): CarbonImmutable
  {
    [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

    return $date->setTime($hour, $minute);
  }
}
