<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\MedicalService;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BookingController extends Controller
{
  public function show(Request $request): View
  {
    $selectedService = $request->integer('service_id')
      ? MedicalService::with('specialty')->where('is_active', true)->find($request->integer('service_id'))
      : null;
    $selectedSlot = $selectedService ? $this->selectedSlotFor($selectedService, $request->integer('slot_id')) : null;
    $selectedPeriod = $this->resolveSelectedPeriod($request);
    $weekStart = $this->resolveWeekStart($request, $selectedService, $selectedSlot);
    $selectedDate = $request->query('date') ?: $selectedSlot?->start_at->toDateString();
    $visibleMonth = $this->resolveVisibleMonth($request, $weekStart, $selectedSlot);
    $weekDays = $this->weekDaysFor($weekStart, $selectedService, $selectedPeriod);

    return view('patient.booking', [
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $selectedService,
      'selectedDate' => $selectedDate,
      'selectedSlot' => $selectedSlot,
      'selectedPeriod' => $selectedPeriod,
      'weekStart' => $weekStart,
      'visibleMonth' => $visibleMonth,
      'availableMonths' => $selectedService ? $this->availableMonthsFor($selectedService) : collect(),
      'weekDays' => $weekDays,
      'weekSlots' => $this->allWeekSlotsFor($weekDays, $selectedService, $selectedPeriod),
      'weekPartialUrl' => url('/patient/book/week'),
      'baseUrl' => url('/patient/book'),
      'formAction' => '/appointments',
      'formMethod' => 'POST',
      'submitLabel' => 'Prenota',
    ]);
  }

  public function week(Request $request): View
  {
    $selectedService = $request->integer('service_id')
      ? MedicalService::with('specialty')->where('is_active', true)->find($request->integer('service_id'))
      : null;
    $selectedPeriod = $this->resolveSelectedPeriod($request);
    $weekStart = $this->resolveWeekStart($request, $selectedService, null);
    $selectedDate = $request->query('date') ?: null;
    $visibleMonth = $this->resolveVisibleMonth($request, $weekStart, null);
    $weekDays = $this->weekDaysFor($weekStart, $selectedService, $selectedPeriod);

    return view('patient.partials.booking-week-partial', [
      'selectedService' => $selectedService,
      'selectedDate' => $selectedDate,
      'selectedSlot' => null,
      'selectedPeriod' => $selectedPeriod,
      'weekStart' => $weekStart,
      'visibleMonth' => $visibleMonth,
      'availableMonths' => $selectedService ? $this->availableMonthsFor($selectedService) : collect(),
      'weekDays' => $weekDays,
      'weekSlots' => $this->allWeekSlotsFor($weekDays, $selectedService, $selectedPeriod),
      'weekPartialUrl' => url('/patient/book/week'),
      'baseUrl' => url('/patient/book'),
    ]);
  }

  public function store(Request $request, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'slot_id' => ['required', 'integer', 'exists:availability_slots,id'],
      'service_id' => ['required', 'integer', 'exists:medical_services,id'],
      'notes' => ['nullable', 'string'],
    ]);

    $appointments->book(
      $request->user()->patientProfile,
      (int) $validated['slot_id'],
      (int) $validated['service_id'],
      $validated['notes'] ?? '',
    );

    return redirect('/patient/appointments')->with('status', 'Appuntamento confermato.');
  }

  private function resolveWeekStart(Request $request, ?MedicalService $service, ?AvailabilitySlot $selectedSlot): CarbonImmutable
  {
    if ($request->filled('week_start')) {
      return CarbonImmutable::parse((string) $request->string('week_start'))->startOfDay();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfDay();
    }

    if ($request->filled('month')) {
      $month = CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();

      return $service ? $this->firstWeekWithAvailabilityInMonth($service, $month) : $this->firstWorkingWeekOfMonth($month);
    }

    $today = CarbonImmutable::now();

    return $today->isWeekend()
      ? $today->next(CarbonImmutable::MONDAY)->startOfDay()
      : $today->startOfWeek(CarbonImmutable::MONDAY);
  }

  private function resolveVisibleMonth(Request $request, CarbonImmutable $weekStart, ?AvailabilitySlot $selectedSlot): CarbonImmutable
  {
    if ($request->filled('month')) {
      return CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfMonth();
    }

    return $weekStart->startOfMonth();
  }

  private function resolveSelectedPeriod(Request $request): string
  {
    return in_array($request->query('period'), ['all', 'mattina', 'pomeriggio'], true)
      ? (string) $request->query('period')
      : 'all';
  }

  private function weekDaysFor(CarbonImmutable $weekStart, ?MedicalService $service, string $selectedPeriod): Collection
  {
    return collect(range(0, 13))
      ->map(fn (int $offset) => $weekStart->addDays($offset))
      ->reject(fn (CarbonImmutable $date) => $date->isWeekend())
      ->take(5)
      ->values()
      ->map(fn (CarbonImmutable $date): array => [
        'date' => $date,
        'hasSlots' => $service ? $this->availableSlotsFor($service, $date->toDateString())->isNotEmpty() : false,
      ]);
  }

  private function allWeekSlotsFor(Collection $weekDays, ?MedicalService $service, string $selectedPeriod): Collection
  {
    return $weekDays->mapWithKeys(function (array $day) use ($service, $selectedPeriod): array {
      $dateStr = $day['date']->toDateString();

      return [$dateStr => $service ? $this->availableSlotsFor($service, $dateStr, $selectedPeriod) : collect()];
    });
  }

  private function availableSlotsFor(MedicalService $service, string $date, string $selectedPeriod = 'all'): Collection
  {
    $slots = $this->availableSlotQuery($service)
      ->whereDate('start_at', $date)
      ->orderBy('start_at')
      ->get();

    if ($selectedPeriod === 'mattina') {
      return $slots->filter(fn (AvailabilitySlot $slot) => $slot->start_at->hour < 13)->values();
    }

    if ($selectedPeriod === 'pomeriggio') {
      return $slots->filter(fn (AvailabilitySlot $slot) => $slot->start_at->hour >= 13)->values();
    }

    return $slots;
  }

  private function selectedSlotFor(MedicalService $service, int $slotId): ?AvailabilitySlot
  {
    if (! $slotId) {
      return null;
    }

    return $this->availableSlotQuery($service)
      ->whereKey($slotId)
      ->first();
  }

  private function availableMonthsFor(MedicalService $service): Collection
  {
    return $this->availableSlotQuery($service)
      ->orderBy('start_at')
      ->get(['start_at'])
      ->map(fn (AvailabilitySlot $slot) => CarbonImmutable::parse($slot->start_at)->startOfMonth())
      ->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
      ->values();
  }

  private function firstWeekWithAvailabilityInMonth(MedicalService $service, CarbonImmutable $month): CarbonImmutable
  {
    $slot = $this->availableSlotQuery($service)
      ->whereBetween('start_at', [$month->startOfMonth(), $month->endOfMonth()])
      ->orderBy('start_at')
      ->first();

    return $slot
      ? CarbonImmutable::parse($slot->start_at)->startOfDay()
      : $this->firstWorkingWeekOfMonth($month);
  }

  private function firstWorkingWeekOfMonth(CarbonImmutable $month): CarbonImmutable
  {
    $firstDay = $month->startOfMonth();
    $firstWorkingDay = $firstDay->isWeekend() ? $firstDay->next(CarbonImmutable::MONDAY) : $firstDay;

    return $firstWorkingDay->startOfDay();
  }

  private function availableSlotQuery(MedicalService $service)
  {
    return AvailabilitySlot::query()
      ->with(['doctor', 'clinic'])
      ->publicAvailable()
      ->whereHas('doctor.services', fn ($services) => $services
        ->where('medical_services.id', $service->id)
        ->where('is_active', true));
  }
}
