<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
  public function show(Request $request): View
  {
    $mode = $request->query('mode') === 'doctor' ? 'doctor' : 'service';

    return view('patient.booking', [
      'mode' => $mode,
      'services' => MedicalService::with('specialty')->where('is_active', true)->orderBy('name')->get(),
      'doctors' => DoctorProfile::with(['specialty', 'services' => fn ($query) => $query->where('is_active', true)])
        ->where('is_active', true)
        ->orderBy('display_name')
        ->get(),
    ]);
  }

  public function store(Request $request, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'slot_id' => ['required', 'integer', 'exists:availability_slots,id'],
      'service_id' => ['required', 'integer', 'exists:medical_services,id'],
      'notes' => ['nullable', 'string'],
    ]);

    $appointments->book(
      $request->user()->patientProfile,
      (int) $validated['slot_id'],
      (int) $validated['service_id'],
      $validated['notes'] ?? '',
    );

    return redirect('/patient/appointments')->with('status', 'Appuntamento confermato.');
  }
}
