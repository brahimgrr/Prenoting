<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->filter(fn(Appointment $appointment) => in_array($appointment->status, Appointment::ACTIVE_SLOT_STATUSES, true) && $appointment->start_at->isFuture())
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
            'phone' => ValidationRules::phone(),
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => 'Inserisci un numero di telefono valido.',
        ]);

        $request->user()->patientProfile->forceFill([
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? '',
        ])->save();

        return redirect('/patient/profile')->with('status', 'Profilo aggiornato.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $currentPassword = $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Inserisci la password attuale.',
        ]);

        if (!Hash::check($currentPassword['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La password attuale non e corretta.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Inserisci una nuova password.',
            'password.min' => 'La password deve contenere almeno 8 caratteri.',
            'password.confirmed' => 'La conferma della password non corrisponde.',
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect('/patient/profile')->with('status', 'Password aggiornata.');
    }
}
