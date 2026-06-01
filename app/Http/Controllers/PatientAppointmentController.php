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
        $historyFilter = $this->historyFilter($request);
        $appointments = Appointment::withPortalRelations()
            ->where('patient_id', $request->user()->patientProfile->id)
            ->orderByDesc('start_at')
            ->get();

        $upcoming = $appointments
            ->filter(fn(Appointment $appointment) => in_array($appointment->status, Appointment::ACTIVE_SLOT_STATUSES, true) && $appointment->start_at->isFuture())
            ->sortBy('start_at')
            ->values();
        $pastAppointments = $appointments->diff($upcoming)->values();

        $viewData = [
            'upcomingAppointments' => $upcoming,
            'pastAppointments' => $this->filteredHistoryAppointments($pastAppointments, $historyFilter),
            'historyFilter' => $historyFilter,
            'historyFilters' => $this->historyFilters(),
        ];

        if ($request->ajax()) {
            return view('patient.partials.appointments-history', $viewData);
        }

        return view('patient.appointments', $viewData);
    }

    private function historyFilter(Request $request): string
    {
        $filter = $request->query('history_filter', 'all');

        return in_array($filter, array_keys($this->historyFilters()), true) ? $filter : 'all';
    }

    private function filteredHistoryAppointments($appointments, string $filter)
    {
        return match ($filter) {
            'past' => $appointments
                ->filter(fn(Appointment $appointment): bool => $appointment->status !== Appointment::STATUS_CANCELLED)
                ->values(),
            'cancelled_by_patient' => $appointments
                ->filter(fn(Appointment $appointment): bool => $appointment->status === Appointment::STATUS_CANCELLED
                    && $appointment->cancelled_by_role === Appointment::CANCELLED_BY_PATIENT)
                ->values(),
            'cancelled_by_doctor' => $appointments
                ->filter(fn(Appointment $appointment): bool => $appointment->status === Appointment::STATUS_CANCELLED
                    && $appointment->cancelled_by_role === Appointment::CANCELLED_BY_DOCTOR)
                ->values(),
            default => $appointments,
        };
    }

    private function historyFilters(): array
    {
        return [
            'all' => 'Tutti',
            'past' => 'Passati',
            'cancelled_by_patient' => 'Annullati da te',
            'cancelled_by_doctor' => 'Annullati dal medico',
        ];
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

    private function authorizePatientAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->patient_id === $request->user()->patientProfile->id, 404);
    }

    public function reschedule(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->authorizePatientAppointment($request, $appointment);
        $validated = $request->validate([
            'slot_start' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        $appointments->reschedule($appointment, (string)$validated['slot_start']);

        return redirect('/patient/appointments')->with('status', 'Appuntamento spostato.');
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
}
