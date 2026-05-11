<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Services\AvailabilityService;
use App\Services\AppointmentService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PatientAppointmentController extends Controller
{
  private const AVAILABLE_DAYS_PAGE_SIZE = 5;

  public function __construct(private readonly AvailabilityService $availability)
  {
  }

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
    $appointment->loadMissing(['service']);
    $doctor = $appointment->doctor ?? $this->availability->primaryDoctor();
    $service = $appointment->service;
    $selectedSlot = $service ? $this->selectedSlotFor($doctor, $service, (string) $request->query('slot_start', ''), $appointment) : null;
    $selectedPeriod = $this->resolveSelectedPeriod($request);
    $allAvailableDates = $service ? $this->availableDatesFor($doctor, $service, $appointment) : collect();
    $filteredAvailableDates = $service ? $this->availableDatesFor($doctor, $service, $appointment, $selectedPeriod) : collect();
    $visibleDates = $filteredAvailableDates->isNotEmpty() ? $filteredAvailableDates : $allAvailableDates;
    $weekStart = $this->resolveWeekStart($request, $service, $selectedSlot, $appointment, $visibleDates);
    $weekDays = $service ? $this->weekDaysFor($visibleDates, $weekStart) : collect();
    $selectedDate = $this->resolveSelectedDate($request, $selectedSlot, $weekDays);
    $visibleMonth = $this->resolveVisibleMonth($request, $weekDays, $selectedSlot);

    return view('patient.appointment-edit', [
      'appointment' => $appointment,
      'services' => MedicalService::where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $service,
      'selectedDate' => $selectedDate,
      'selectedSlot' => $selectedSlot,
      'selectedPeriod' => $selectedPeriod,
      'weekStart' => $weekStart,
      'previousWeekStart' => $this->previousWeekStartFor($visibleDates, $weekStart),
      'nextWeekStart' => $this->nextWeekStartFor($visibleDates, $weekStart),
      'visibleMonth' => $visibleMonth,
      'availableMonths' => $service ? $this->availableMonthsFor($allAvailableDates) : collect(),
      'weekDays' => $weekDays,
      'weekSlots' => $this->allWeekSlotsFor($doctor, $weekDays, $service, $appointment, $selectedPeriod),
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

    $appointments->cancelByPatient($appointment, $validated['cancellation_reason'] ?? '', $request->user());

    return redirect('/patient/appointments')->with('status', 'Appuntamento annullato.');
  }

  public function reschedule(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $this->authorizePatientAppointment($request, $appointment);
    $validated = $request->validate([
      'slot_start' => ['required', 'date_format:Y-m-d\TH:i'],
    ]);

    $appointments->reschedule($appointment, (string) $validated['slot_start']);

    return redirect('/patient/appointments')->with('status', 'Appuntamento spostato.');
  }

  private function authorizePatientAppointment(Request $request, Appointment $appointment): void
  {
    abort_unless($appointment->patient_id === $request->user()->patientProfile->id, 404);
  }

  private function resolveWeekStart(Request $request, ?MedicalService $service, ?VirtualAvailabilitySlot $selectedSlot, Appointment $appointment, Collection $availableDates): CarbonImmutable
  {
    if ($request->filled('week_start')) {
      $requested = CarbonImmutable::parse((string) $request->string('week_start'))->startOfDay();
      $match = $availableDates->first(fn (CarbonImmutable $date) => $date->equalTo($requested));

      if ($match) {
        return $match;
      }
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfDay();
    }

    if ($request->filled('month')) {
      $month = CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();
      $monthStart = $availableDates->first(fn (CarbonImmutable $date) => $date->betweenIncluded($month->startOfMonth(), $month->endOfMonth()));
      if ($monthStart) {
        return $monthStart;
      }
    }

    return $availableDates->first() ?? CarbonImmutable::now()->startOfDay();
  }

  private function resolveVisibleMonth(Request $request, Collection $weekDays, ?VirtualAvailabilitySlot $selectedSlot): CarbonImmutable
  {
    if ($request->filled('month')) {
      return CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->startOfMonth();
    }

    $firstVisibleDay = $weekDays->first();
    $firstVisibleDate = is_array($firstVisibleDay) ? ($firstVisibleDay['date'] ?? null) : null;

    return $firstVisibleDate?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
  }

  private function resolveSelectedPeriod(Request $request): string
  {
    return in_array($request->query('period'), ['all', 'mattina', 'pomeriggio'], true)
      ? (string) $request->query('period')
      : 'all';
  }

  private function weekDaysFor(Collection $availableDates, CarbonImmutable $weekStart): Collection
  {
    $startIndex = $this->availableDateIndex($availableDates, $weekStart);

    return $availableDates
      ->slice($startIndex, self::AVAILABLE_DAYS_PAGE_SIZE)
      ->values()
      ->map(fn (CarbonImmutable $date): array => [
        'date' => $date,
        'hasSlots' => true,
      ]);
  }

  private function previousWeekStartFor(Collection $availableDates, CarbonImmutable $weekStart): ?CarbonImmutable
  {
    $startIndex = $this->availableDateIndex($availableDates, $weekStart);

    if ($startIndex <= 0) {
      return null;
    }

    return $availableDates->get(max(0, $startIndex - self::AVAILABLE_DAYS_PAGE_SIZE));
  }

  private function nextWeekStartFor(Collection $availableDates, CarbonImmutable $weekStart): ?CarbonImmutable
  {
    $startIndex = $this->availableDateIndex($availableDates, $weekStart);
    $nextIndex = $startIndex + self::AVAILABLE_DAYS_PAGE_SIZE;

    if ($nextIndex >= $availableDates->count()) {
      return null;
    }

    return $availableDates->get($nextIndex);
  }

  private function availableDateIndex(Collection $availableDates, CarbonImmutable $weekStart): int
  {
    $index = $availableDates->search(fn (CarbonImmutable $date) => $date->equalTo($weekStart));

    return $index === false ? 0 : $index;
  }

  private function allWeekSlotsFor(DoctorProfile $doctor, Collection $weekDays, ?MedicalService $service, Appointment $appointment, string $selectedPeriod): Collection
  {
    return $weekDays->mapWithKeys(function (array $day) use ($doctor, $service, $appointment, $selectedPeriod): array {
      $dateStr = $day['date']->toDateString();

      return [$dateStr => $service ? $this->availableSlotsFor($doctor, $service, $dateStr, $appointment, $selectedPeriod) : collect()];
    });
  }

  private function availableSlotsFor(DoctorProfile $doctor, MedicalService $service, string $date, Appointment $appointment, string $selectedPeriod = 'all'): Collection
  {
    $slots = $this->availability->availableSlotsForDate($doctor, $service, $date, $appointment);

    if ($selectedPeriod === 'mattina') {
      return $slots->filter(fn (VirtualAvailabilitySlot $slot) => $slot->start_at->hour < 13)->values();
    }

    if ($selectedPeriod === 'pomeriggio') {
      return $slots->filter(fn (VirtualAvailabilitySlot $slot) => $slot->start_at->hour >= 13)->values();
    }

    return $slots;
  }

  private function selectedSlotFor(DoctorProfile $doctor, MedicalService $service, string $slotStart, Appointment $appointment): ?VirtualAvailabilitySlot
  {
    if ($slotStart === '') {
      return null;
    }

    return $this->availability->availableSlotByKey($doctor, $service, $slotStart, $appointment);
  }

  private function availableMonthsFor(Collection $availableDates): Collection
  {
    return $availableDates
      ->map(fn (CarbonImmutable $date) => $date->startOfMonth())
      ->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
      ->values();
  }

  private function resolveSelectedDate(Request $request, ?VirtualAvailabilitySlot $selectedSlot, Collection $weekDays): ?string
  {
    $requestedDate = $request->query('date');

    if ($requestedDate && $weekDays->contains(fn (array $day) => $day['date']->toDateString() === $requestedDate)) {
      return $requestedDate;
    }

    if ($selectedSlot) {
      return CarbonImmutable::parse($selectedSlot->start_at)->toDateString();
    }

    $firstVisibleDay = $weekDays->first();
    $firstVisibleDate = is_array($firstVisibleDay) ? ($firstVisibleDay['date'] ?? null) : null;

    return $firstVisibleDate?->toDateString();
  }

  private function availableDatesFor(DoctorProfile $doctor, MedicalService $service, Appointment $appointment, string $selectedPeriod = 'all'): Collection
  {
    return $this->availability->availableDates($doctor, $service, $selectedPeriod, $appointment);
  }
}
