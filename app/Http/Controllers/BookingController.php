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
    $weekStart = $this->resolveWeekStart($request, $selectedService, $selectedSlot);
    $selectedDate = $request->query('date') ?: $selectedSlot?->start_at->toDateString();
    $slots = $selectedService && $selectedDate
      ? $this->availableSlotsFor($selectedService, $selectedDate)
      : collect();
    $visibleMonth = $this->resolveVisibleMonth($request, $weekStart, $selectedSlot);

    return view('patient.booking', [
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $selectedService,
      'selectedDate' => $selectedDate,
      'selectedSlot' => $selectedSlot,
      'weekStart' => $weekStart,
      'visibleMonth' => $visibleMonth,
      'availableMonths' => $selectedService ? $this->availableMonthsFor($selectedService) : collect(),
      'weekDays' => $this->weekDaysFor($weekStart, $selectedService),
      'slots' => $slots,
      'formAction' => '/appointments',
      'formMethod' => 'POST',
      'submitLabel' => 'Prenota',
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
      return CarbonImmutable::parse((string) $request->string('week_start'))->startOfWeek();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfWeek();
    }

    if ($request->filled('month')) {
      $month = CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();

      return $service ? $this->firstWeekWithAvailabilityInMonth($service, $month) : $this->firstWorkingWeekOfMonth($month);
    }

    $today = CarbonImmutable::now();

    return $today->isWeekend()
      ? $today->next(CarbonImmutable::MONDAY)->startOfDay()
      : $today->startOfWeek();
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

  private function weekDaysFor(CarbonImmutable $weekStart, ?MedicalService $service): Collection
  {
    return collect(range(0, 4))->map(function (int $offset) use ($weekStart, $service): array {
      $date = $weekStart->addDays($offset);

      return [
        'date' => $date,
        'hasSlots' => $service ? $this->availableSlotsFor($service, $date->toDateString())->isNotEmpty() : false,
      ];
    });
  }

  private function availableSlotsFor(MedicalService $service, string $date): Collection
  {
    return $this->availableSlotQuery($service)
      ->whereDate('start_at', $date)
      ->orderBy('start_at')
      ->get();
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
      ? CarbonImmutable::parse($slot->start_at)->startOfWeek()
      : $this->firstWorkingWeekOfMonth($month);
  }

  private function firstWorkingWeekOfMonth(CarbonImmutable $month): CarbonImmutable
  {
    $firstDay = $month->startOfMonth();
    $firstWorkingDay = $firstDay->isWeekend() ? $firstDay->next(CarbonImmutable::MONDAY) : $firstDay;

    return $firstWorkingDay->startOfWeek();
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
