<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
  public function operations(Request $request): View
  {
    return $this->viewAppointments($request, 'operations');
  }

  public function appointments(Request $request): View
  {
    return $this->viewAppointments($request, 'appointments');
  }

  public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'status' => ['required', 'string', Rule::in(Appointment::ALL_STATUSES)],
    ]);

    $appointments->updateByStaff($appointment, $validated['status'], $request->user());

    return redirect('/staff/appointments')->with('status', 'Stato appuntamento aggiornato.');
  }

  private function viewAppointments(Request $request, string $mode): View
  {
    $validated = $request->validate([
      'date' => ['nullable', 'date_format:Y-m-d'],
      'clinic' => ['nullable', 'integer', 'min:1'],
      'doctor' => ['nullable', 'integer', 'min:1'],
      'service' => ['nullable', 'integer', 'min:1'],
      'status' => ['nullable', Rule::in(Appointment::ALL_STATUSES)],
    ]);

    $filters = [
      'date' => $validated['date'] ?? ($mode === 'operations' ? now()->toDateString() : ''),
      'clinic' => $validated['clinic'] ?? '',
      'doctor' => $validated['doctor'] ?? '',
      'service' => $validated['service'] ?? '',
      'status' => $validated['status'] ?? '',
    ];

    $appointments = Appointment::withPortalRelations()
      ->when($filters['date'], fn ($query, $date) => $query->whereDate('start_at', $date))
      ->when($filters['clinic'], fn ($query, $clinic) => $query->where('clinic_id', $clinic))
      ->when($filters['doctor'], fn ($query, $doctor) => $query->where('doctor_id', $doctor))
      ->when($filters['service'], fn ($query, $service) => $query->where('service_id', $service))
      ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
      ->orderBy('start_at')
      ->get();

    return view('staff.dashboard', [
      'mode' => $mode,
      'filters' => $filters,
      'appointments' => $appointments,
    ]);
  }
}
