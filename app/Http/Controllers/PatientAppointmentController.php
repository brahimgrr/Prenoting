<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\PatientBookingWizardViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientAppointmentController extends Controller
{
  public function __construct(
    private readonly PatientBookingWizardViewData $bookingWizard,
  )
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
    $viewData = $this->bookingWizard->reschedule($request, $appointment);

    if ($request->ajax()) {
      return view('patient.partials.booking-wizard', $viewData);
    }

    return view('patient.appointment-edit', $viewData);
  }

  public function editWeek(Request $request, Appointment $appointment): View
  {
    $this->authorizePatientAppointment($request, $appointment);

    return view('patient.partials.booking-week-partial', array_replace($this->bookingWizard->reschedule($request, $appointment), [
      'selectedSlot' => null,
    ]));
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
}
