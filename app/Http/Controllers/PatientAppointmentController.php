<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\MedicalService;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PatientAppointmentController extends Controller
{
  public function index(Request $request): View
  {
    $appointments = Appointment::withPortalRelations()
      ->where('patient_id', $request->user()->patientProfile->id)
      ->orderByDesc('start_at')
      ->get();

    $upcoming = $appointments
      ->filter(fn (Appointment $appointment) => in_array($appointment->status, Appointment::ACTIVE_SLOT_STATUSES, true) && $appointment->start_at->isFuture())
      ->sortBy('start_at')
      ->values();

    return view('patient.appointments', [
      'upcomingAppointments' => $upcoming,
      'pastAppointments' => $appointments->diff($upcoming)->values(),
    ]);
  }

  public function edit(Request $request, Appointment $appointment): View
  {
    $this->authorizePatientAppointment($request, $appointment);
    $appointment->loadMissing(['service.specialty', 'doctor', 'clinic']);
    $service = $appointment->service;
    $selectedSlot = $service ? $this->selectedSlotFor($service, $request->integer('slot_id'), $appointment) : null;
    $selectedPeriod = $this->resolveSelectedPeriod($request);
    $weekStart = $this->resolveWeekStart($request, $service, $selectedSlot, $appointment);
    $selectedDate = $request->query('date') ?: $selectedSlot?->start_at->toDateString();
    $visibleMonth = $this->resolveVisibleMonth($request, $weekStart, $selectedSlot);
    $weekDays = $this->weekDaysFor($weekStart, $service, $appointment, $selectedPeriod);

    return view('patient.appointment-edit', [
      'appointment' => $appointment,
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $service,
      'selectedDate' => $selectedDate,
      'selectedSlot' => $selectedSlot,
      'selectedPeriod' => $selectedPeriod,
      'weekStart' => $weekStart,
      'visibleMonth' => $visibleMonth,
      'availableMonths' => $service ? $this->availableMonthsFor($service, $appointment) : collect(),
      'weekDays' => $weekDays,
      'weekSlots' => $this->allWeekSlotsFor($weekDays, $service, $appointment, $selectedPeriod),
      'weekPartialUrl' => null,
      'baseUrl' => url("/appointments/{$appointment->id}/edit"),
      'formAction' => "/appointments/{$appointment->id}/reschedule",
      'formMethod' => 'POST',
      'submitLabel' => 'Sposta',
    ]);
  }

  public function cancel(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $this->authorizePatientAppointment($request, $appointment);
    $validated = $request->validate([
      'cancellation_reason' => ['nullable', 'string'],
    ]);

    $appointments->cancelByPatient($appointment, $request->user(), $validated['cancellation_reason'] ?? '');

    return redirect('/patient/appointments')->with('status', 'Appuntamento annullato.');
  }

  public function reschedule(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $this->authorizePatientAppointment($request, $appointment);
    $validated = $request->validate([
      'slot_id' => ['required', 'integer', 'exists:availability_slots,id'],
    ]);

    $appointments->reschedule($appointment, (int) $validated['slot_id'], $request->user());

    return redirect('/patient/appointments')->with('status', 'Appuntamento spostato.');
  }

  private function authorizePatientAppointment(Request $request, Appointment $appointment): void
  {
    abort_unless($appointment->patient_id === $request->user()->patientProfile->id, 404);
  }

  private function resolveWeekStart(Request $request, ?MedicalService $service, ?AvailabilitySlot $selectedSlot, Appointment $appointment): CarbonImmutable
  {
    if ($request->filled('week_start')) {
      return CarbonImmutable::parse((string) $request->string('week_start'))->startOfDay();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfDay();
    }

    if ($request->filled('month')) {
      $month = CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();

      return $service ? $this->firstWeekWithAvailabilityInMonth($service, $month, $appointment) : $this->firstWorkingWeekOfMonth($month);
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

  private function weekDaysFor(CarbonImmutable $weekStart, ?MedicalService $service, Appointment $appointment, string $selectedPeriod): Collection
  {
    return collect(range(0, 13))
      ->map(fn (int $offset) => $weekStart->addDays($offset))
      ->reject(fn (CarbonImmutable $date) => $date->isWeekend())
      ->take(5)
      ->values()
      ->map(fn (CarbonImmutable $date): array => [
        'date' => $date,
        'hasSlots' => $service ? $this->availableSlotsFor($service, $date->toDateString(), $appointment)->isNotEmpty() : false,
      ]);
  }

  private function allWeekSlotsFor(Collection $weekDays, ?MedicalService $service, Appointment $appointment, string $selectedPeriod): Collection
  {
    return $weekDays->mapWithKeys(function (array $day) use ($service, $appointment, $selectedPeriod): array {
      $dateStr = $day['date']->toDateString();

      return [$dateStr => $service ? $this->availableSlotsFor($service, $dateStr, $appointment, $selectedPeriod) : collect()];
    });
  }

  private function availableSlotsFor(MedicalService $service, string $date, Appointment $appointment, string $selectedPeriod = 'all'): Collection
  {
    $slots = $this->availableSlotQuery($service, $appointment)
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

  private function selectedSlotFor(MedicalService $service, int $slotId, Appointment $appointment): ?AvailabilitySlot
  {
    if (! $slotId) {
      return null;
    }

    return $this->availableSlotQuery($service, $appointment)
      ->whereKey($slotId)
      ->first();
  }

  private function availableMonthsFor(MedicalService $service, Appointment $appointment): Collection
  {
    return $this->availableSlotQuery($service, $appointment)
      ->orderBy('start_at')
      ->get(['start_at'])
      ->map(fn (AvailabilitySlot $slot) => CarbonImmutable::parse($slot->start_at)->startOfMonth())
      ->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
      ->values();
  }

  private function firstWeekWithAvailabilityInMonth(MedicalService $service, CarbonImmutable $month, Appointment $appointment): CarbonImmutable
  {
    $slot = $this->availableSlotQuery($service, $appointment)
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

  private function availableSlotQuery(MedicalService $service, Appointment $appointment)
  {
    return AvailabilitySlot::query()
      ->with(['doctor', 'clinic'])
      ->publicAvailable()
      ->whereKeyNot($appointment->slot_id)
      ->whereHas('doctor.services', fn ($services) => $services
        ->where('medical_services.id', $service->id)
        ->where('is_active', true));
  }
}
