<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function cancel(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->authorizePatientAppointment($request, $appointment);
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        $appointments->cancelByPatient($appointment, $validated['cancellation_reason'] ?? '', $request->user());

        return redirect('/patient/appointments')->with('status', 'Appuntamento annullato.');
    }

    private function authorizePatientAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->patient_id === $request->user()->patientProfile->id, 404);
    }
}
