<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PatientBookingCalendarService
{
  private const AVAILABLE_DAYS_PAGE_SIZE = 5;

  public function __construct(private readonly AvailabilityService $availability)
  {
  }

  public function build(
    DoctorProfile $doctor,
    ?MedicalService $service,
    array $query = [],
    ?Appointment $excludingAppointment = null,
  ): array {
    $selectedPeriod = $this->selectedPeriod($query);
    $selectedSlot = $service
      ? $this->selectedSlotFor($doctor, $service, $this->stringQuery($query, 'slot_start'), $excludingAppointment)
      : null;
    $allAvailableDates = $service ? $this->availability->availableDates($doctor, $service, excludingAppointment: $excludingAppointment) : collect();
    $filteredAvailableDates = $service ? $this->availability->availableDates($doctor, $service, $selectedPeriod, $excludingAppointment) : collect();
    $visibleDates = $filteredAvailableDates->isNotEmpty() ? $filteredAvailableDates : $allAvailableDates;
    $weekStart = $this->weekStart($query, $selectedSlot, $visibleDates);
    $weekDays = $service ? $this->weekDaysFor($visibleDates, $weekStart) : collect();

    return [
      'selectedService' => $service,
      'selectedDate' => $this->selectedDate($query, $selectedSlot, $weekDays),
      'selectedSlot' => $selectedSlot,
      'selectedPeriod' => $selectedPeriod,
      'weekStart' => $weekStart,
      'previousWeekStart' => $this->previousWeekStartFor($visibleDates, $weekStart),
      'nextWeekStart' => $this->nextWeekStartFor($visibleDates, $weekStart),
      'visibleMonth' => $this->visibleMonth($query, $weekDays, $selectedSlot),
      'availableMonths' => $service ? $this->availableMonthsFor($allAvailableDates) : collect(),
      'weekDays' => $weekDays,
      'weekSlots' => $this->weekSlotsFor($doctor, $weekDays, $service, $excludingAppointment, $selectedPeriod),
    ];
  }

  private function weekStart(array $query, ?VirtualAvailabilitySlot $selectedSlot, Collection $availableDates): CarbonImmutable
  {
    if ($this->stringQuery($query, 'week_start') !== '') {
      $requested = CarbonImmutable::parse($this->stringQuery($query, 'week_start'))->startOfDay();
      $match = $availableDates->first(fn (CarbonImmutable $date) => $date->equalTo($requested));

      if ($match) {
        return $match;
      }
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfDay();
    }

    if ($this->stringQuery($query, 'month') !== '') {
      $month = CarbonImmutable::createFromFormat('Y-m-d', $this->stringQuery($query, 'month').'-01')->startOfMonth();
      $monthStart = $availableDates->first(fn (CarbonImmutable $date) => $date->betweenIncluded($month->startOfMonth(), $month->endOfMonth()));

      if ($monthStart) {
        return $monthStart;
      }
    }

    return $availableDates->first() ?? CarbonImmutable::now()->startOfDay();
  }

  private function visibleMonth(array $query, Collection $weekDays, ?VirtualAvailabilitySlot $selectedSlot): CarbonImmutable
  {
    if ($this->stringQuery($query, 'month') !== '') {
      return CarbonImmutable::createFromFormat('Y-m-d', $this->stringQuery($query, 'month').'-01')->startOfMonth();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfMonth();
    }

    $firstVisibleDay = $weekDays->first();
    $firstVisibleDate = is_array($firstVisibleDay) ? ($firstVisibleDay['date'] ?? null) : null;

    return $firstVisibleDate?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
  }

  private function selectedPeriod(array $query): string
  {
    $period = $this->stringQuery($query, 'period');

    return in_array($period, ['all', 'mattina', 'pomeriggio'], true) ? $period : 'all';
  }

  private function weekDaysFor(Collection $availableDates, CarbonImmutable $weekStart): Collection
  {
    return $availableDates
      ->slice($this->availableDateIndex($availableDates, $weekStart), self::AVAILABLE_DAYS_PAGE_SIZE)
      ->values()
      ->map(fn (CarbonImmutable $date): array => [
        'date' => $date,
        'hasSlots' => true,
      ]);
  }

  private function previousWeekStartFor(Collection $availableDates, CarbonImmutable $weekStart): ?CarbonImmutable
  {
    $startIndex = $this->availableDateIndex($availableDates, $weekStart);

    return $startIndex <= 0
      ? null
      : $availableDates->get(max(0, $startIndex - self::AVAILABLE_DAYS_PAGE_SIZE));
  }

  private function nextWeekStartFor(Collection $availableDates, CarbonImmutable $weekStart): ?CarbonImmutable
  {
    $nextIndex = $this->availableDateIndex($availableDates, $weekStart) + self::AVAILABLE_DAYS_PAGE_SIZE;

    return $nextIndex >= $availableDates->count() ? null : $availableDates->get($nextIndex);
  }

  private function availableDateIndex(Collection $availableDates, CarbonImmutable $weekStart): int
  {
    $index = $availableDates->search(fn (CarbonImmutable $date) => $date->equalTo($weekStart));

    return $index === false ? 0 : $index;
  }

  private function weekSlotsFor(
    DoctorProfile $doctor,
    Collection $weekDays,
    ?MedicalService $service,
    ?Appointment $excludingAppointment,
    string $selectedPeriod,
  ): Collection {
    return $weekDays->mapWithKeys(function (array $day) use ($doctor, $service, $excludingAppointment, $selectedPeriod): array {
      $dateStr = $day['date']->toDateString();

      return [$dateStr => $service
        ? $this->availableSlotsFor($doctor, $service, $dateStr, $excludingAppointment, $selectedPeriod)
        : collect()];
    });
  }

  private function availableSlotsFor(
    DoctorProfile $doctor,
    MedicalService $service,
    string $date,
    ?Appointment $excludingAppointment,
    string $selectedPeriod,
  ): Collection {
    return $this->availability->filterSlotsByPeriod(
      $this->availability->availableSlotsForDate($doctor, $service, $date, $excludingAppointment),
      $selectedPeriod,
    );
  }

  private function selectedSlotFor(
    DoctorProfile $doctor,
    MedicalService $service,
    string $slotStart,
    ?Appointment $excludingAppointment,
  ): ?VirtualAvailabilitySlot {
    return $slotStart === ''
      ? null
      : $this->availability->availableSlotByKey($doctor, $service, $slotStart, $excludingAppointment);
  }

  private function availableMonthsFor(Collection $availableDates): Collection
  {
    return $availableDates
      ->map(fn (CarbonImmutable $date) => $date->startOfMonth())
      ->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
      ->values();
  }

  private function selectedDate(array $query, ?VirtualAvailabilitySlot $selectedSlot, Collection $weekDays): ?string
  {
    $requestedDate = $this->stringQuery($query, 'date');

    if ($requestedDate !== '' && $weekDays->contains(fn (array $day) => $day['date']->toDateString() === $requestedDate)) {
      return $requestedDate;
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->toDateString();
    }

    $firstVisibleDay = $weekDays->first();
    $firstVisibleDate = is_array($firstVisibleDay) ? ($firstVisibleDay['date'] ?? null) : null;

    return $firstVisibleDate?->toDateString();
  }

  private function stringQuery(array $query, string $key): string
  {
    $value = $query[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
  }
}
