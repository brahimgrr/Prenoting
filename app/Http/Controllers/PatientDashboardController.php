<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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

  public function profile(Request $request): View
  {
    return view('patient.profile', [
      'profile' => $request->user()->patientProfile,
    ]);
  }

  public function updateProfile(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
      'phone' => ['required', 'string', 'max:32'],
      'address' => ['nullable', 'string', 'max:255'],
    ]);

    $request->user()->forceFill([
      'email' => $validated['email'] ?? null,
    ])->save();

    $request->user()->patientProfile->forceFill([
      'phone' => $validated['phone'],
      'address' => $validated['address'] ?? '',
    ])->save();

    return redirect('/patient/profile')->with('status', 'Profilo aggiornato.');
  }

  public function updatePassword(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'current_password' => ['required', 'string'],
      'password' => ['required', 'confirmed', Password::min(8)],
    ]);

    if (! Hash::check($validated['current_password'], $request->user()->password)) {
      throw ValidationException::withMessages([
        'current_password' => 'La password attuale non e corretta.',
      ]);
    }

    $request->user()->forceFill([
      'password' => $validated['password'],
    ])->save();

    return redirect('/patient/profile')->with('status', 'Password aggiornata.');
  }
}
