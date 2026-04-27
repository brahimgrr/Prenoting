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
    $weekStart = $this->resolveWeekStart($request);
    $selectedDate = $request->query('date');
    $slots = $service && $selectedDate
      ? $this->availableSlotsFor($service, $selectedDate, $appointment)
      : collect();

    return view('patient.appointment-edit', [
      'appointment' => $appointment,
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'selectedService' => $service,
      'selectedDate' => $selectedDate,
      'weekStart' => $weekStart,
      'weekDays' => $this->weekDaysFor($weekStart, $service, $appointment),
      'slots' => $slots,
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

  private function weekDaysFor(CarbonImmutable $weekStart, ?MedicalService $service, Appointment $appointment): Collection
  {
    return collect(range(0, 4))->map(function (int $offset) use ($weekStart, $service, $appointment): array {
      $date = $weekStart->addDays($offset);

      return [
        'date' => $date,
        'hasSlots' => $service ? $this->availableSlotsFor($service, $date->toDateString(), $appointment)->isNotEmpty() : false,
      ];
    });
  }

  private function availableSlotsFor(MedicalService $service, string $date, Appointment $appointment): Collection
  {
    return AvailabilitySlot::query()
      ->with(['doctor', 'clinic'])
      ->publicAvailable()
      ->whereKeyNot($appointment->slot_id)
      ->whereDate('start_at', $date)
      ->whereHas('doctor.services', fn ($services) => $services
        ->where('medical_services.id', $service->id)
        ->where('is_active', true))
      ->orderBy('start_at')
      ->get();
  }
}
