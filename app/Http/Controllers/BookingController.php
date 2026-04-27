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
    $weekStart = $this->resolveWeekStart($request);
    $selectedDate = $request->query('date');
    $slots = $selectedService && $selectedDate
      ? $this->availableSlotsFor($selectedService, $selectedDate)
      : collect();

    return view('patient.booking', [
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $selectedService,
      'selectedDate' => $selectedDate,
      'weekStart' => $weekStart,
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

  private function resolveWeekStart(Request $request): CarbonImmutable
  {
    if ($request->filled('week_start')) {
      return CarbonImmutable::parse((string) $request->string('week_start'))->startOfWeek();
    }

    $today = CarbonImmutable::now();

    return $today->isWeekend()
      ? $today->next(CarbonImmutable::MONDAY)->startOfDay()
      : $today->startOfWeek();
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
    return AvailabilitySlot::query()
      ->with(['doctor', 'clinic'])
      ->publicAvailable()
      ->whereDate('start_at', $date)
      ->whereHas('doctor.services', fn ($services) => $services
        ->where('medical_services.id', $service->id)
        ->where('is_active', true))
      ->orderBy('start_at')
      ->get();
  }
}
