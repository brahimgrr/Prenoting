<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Support\ScheduleTime;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
  public const SLOT_STEP_MINUTES = 30;
  public const BOOKING_HORIZON_DAYS = 90;

  public function __construct(private readonly ScheduleWindowService $windows)
  {
  }

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
      $slots = $this->filterSlotsByPeriod(
        $this->availableSlotsForDate($doctor, $service, $date, $excludingAppointment),
        $selectedPeriod,
      );

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
    return ScheduleTime::parseSlotStart($slotStart);
  }

  public function closuresForDate(DoctorProfile $doctor, CarbonImmutable|string $date): Collection
  {
    return $this->windows->closuresForDate($doctor, $date);
  }

  public function filterSlotsByPeriod(Collection $slots, string $selectedPeriod): Collection
  {
    return $slots
      ->filter(fn (VirtualAvailabilitySlot $slot): bool => $this->matchesPeriod($slot, $selectedPeriod))
      ->values();
  }

  private function generatedSlotsForDate(
    DoctorProfile $doctor,
    CarbonImmutable|string $date,
    int $durationMinutes,
    ?Appointment $excludingAppointment = null,
    bool $includePast = false,
  ): Collection {
    $day = ScheduleTime::normalizeDate($date);
    $windows = $this->windows->openingWindowsForDate($doctor, $day);
    $closures = $this->windows->closureIntervalsForDate($doctor, $day);
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

        if ($this->windows->overlapsAny($start, $end, $closures) || $this->windows->overlapsAny($start, $end, $appointments)) {
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

  private function matchesPeriod(VirtualAvailabilitySlot $slot, string $selectedPeriod): bool
  {
    return match ($selectedPeriod) {
      'mattina' => $slot->start_at->hour < 13,
      'pomeriggio' => $slot->start_at->hour >= 13,
      default => true,
    };
  }
}
