<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $doctor = $this->doctorFor($request);
        $showHistory = $this->showHistory($request);
        $historyFilter = $this->historyFilter($request);
        $appointments = Appointment::withPortalRelations()
            ->forDoctor($doctor);

        $upcoming = (clone $appointments)
            ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES)
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->get();

        $historyAppointments = collect();
        if ($showHistory) {
            $historyAppointments = (clone $appointments)
                ->where(function ($query): void {
                    $query
                        ->whereNotIn('status', Appointment::ACTIVE_SLOT_STATUSES)
                        ->orWhere('start_at', '<=', now());
                })
                ->orderByDesc('start_at')
                ->get();
        }

        $viewData = [
            'upcomingAppointments' => $upcoming,
            'pastAppointments' => $this->filteredHistoryAppointments($historyAppointments, $historyFilter),
            'showHistory' => $showHistory,
            'historyFilter' => $historyFilter,
            'historyFilters' => $this->historyFilters(),
        ];

        if ($request->ajax()) {
            return $showHistory
                ? view('doctor.partials.appointments-history', $viewData)
                : view('doctor.partials.appointments-history-placeholder');
        }

        return view('doctor.appointments', $viewData);
    }

    public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->authorizeDoctorAppointment($request, $appointment);
        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $appointments->updateByDoctor($appointment, $validated['status']);

        return redirect()->back()->with('status', 'Stato appuntamento aggiornato.');
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $this->authorizeDoctorAppointment($request, $appointment);
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        $appointments->cancelByDoctor($appointment, $validated['cancellation_reason'] ?? '', $request->user());

        return redirect()->back()->with('status', 'Appuntamento annullato.');
    }

    private function showHistory(Request $request): bool
    {
        return $request->boolean('show_history') || $request->has('history_filter');
    }

    private function historyFilter(Request $request): string
    {
        $filter = $request->query('history_filter', 'past');

        return in_array($filter, array_keys($this->historyFilters()), true) ? $filter : 'past';
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
            'past' => 'Passati',
            'cancelled_by_patient' => 'Annullati dal paziente',
            'cancelled_by_doctor' => 'Annullati da te',
            'all' => 'Tutti',
        ];
    }

    private function authorizeDoctorAppointment(Request $request, Appointment $appointment): void
    {
        $appointment->loadMissing('service');

        abort_unless($appointment->service?->doctor_profile_id === $this->doctorFor($request)->id, 404);
    }

    private function doctorFor(Request $request): DoctorProfile
    {
        $doctor = $request->user()->doctorProfile;
        abort_unless($doctor, 404);

        return $doctor;
    }
}
