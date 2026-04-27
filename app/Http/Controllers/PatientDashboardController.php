<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientDashboardController extends Controller
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

    return view('patient.dashboard', [
      'appointments' => $appointments,
      'upcomingAppointments' => $upcoming,
      'nextAppointment' => $upcoming->first(),
    ]);
  }
}
