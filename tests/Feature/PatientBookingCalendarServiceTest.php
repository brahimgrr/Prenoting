<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Services\AvailabilityService;
use App\Services\PatientBookingCalendarService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class PatientBookingCalendarServiceTest extends TestCase
{
  public function test_period_filter_keeps_requested_day_visible_even_without_matching_slots(): void
  {
    $doctor = new DoctorProfile(['id' => 1]);
    $service = new MedicalService(['duration_minutes' => 30]);
    $requestedDate = CarbonImmutable::parse('2026-06-01 00:00:00');
    $laterDate = CarbonImmutable::parse('2026-06-02 00:00:00');
    $laterAfternoonSlot = new VirtualAvailabilitySlot(
      '2026-06-02T15:00',
      CarbonImmutable::parse('2026-06-02 15:00:00'),
      CarbonImmutable::parse('2026-06-02 15:30:00'),
      1,
    );

    $availability = Mockery::mock(AvailabilityService::class);
    $availability
      ->shouldReceive('availableSlotByKey')
      ->never();
    $availability
      ->shouldReceive('availableDates')
      ->andReturnUsing(function (
        DoctorProfile $doctorArg,
        MedicalService $serviceArg,
        string $selectedPeriod = 'all',
        mixed $excludingAppointment = null,
      ) use ($requestedDate, $laterDate): Collection {
        return $selectedPeriod === 'pomeriggio'
          ? collect([$laterDate])
          : collect([$requestedDate, $laterDate]);
      });
    $availability
      ->shouldReceive('availableSlotsForDate')
      ->andReturnUsing(function (
        DoctorProfile $doctorArg,
        MedicalService $serviceArg,
        CarbonImmutable|string $date,
        mixed $excludingAppointment = null,
        bool $includePast = false,
        string $selectedPeriod = 'all',
      ) use ($requestedDate, $laterDate, $laterAfternoonSlot): Collection {
        $dateString = $date instanceof CarbonImmutable ? $date->toDateString() : $date;

        if ($selectedPeriod === 'pomeriggio' && $dateString === $laterDate->toDateString()) {
          return collect([$laterAfternoonSlot]);
        }

        return collect();
      });

    $calendar = new PatientBookingCalendarService($availability);

    $viewData = $calendar->build($doctor, $service, [
      'week_start' => $requestedDate->toDateString(),
      'date' => $requestedDate->toDateString(),
      'period' => 'pomeriggio',
    ]);

    $this->assertSame($requestedDate->toDateString(), $viewData['selectedDate']);
    $this->assertTrue($viewData['weekDays']->contains(
      fn (array $day): bool => $day['date']->equalTo($requestedDate),
    ));
    $this->assertCount(0, $viewData['weekSlots'][$requestedDate->toDateString()]);
  }
}
