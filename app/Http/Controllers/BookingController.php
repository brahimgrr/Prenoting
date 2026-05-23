<?php

namespace App\Http\Controllers;

use App\Models\MedicalService;
use App\Services\AvailabilityService;
use App\Services\AppointmentService;
use App\Services\PatientBookingCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
  public function __construct(
    private readonly AvailabilityService $availability,
    private readonly PatientBookingCalendarService $calendar,
  )
  {
  }

  public function show(Request $request): View
  {
    $doctor = $this->availability->primaryDoctor();
    $selectedService = $request->integer('service_id')
      ? MedicalService::where('is_active', true)->find($request->integer('service_id'))
      : null;

    return view('patient.booking', $this->calendar->build($doctor, $selectedService, $request->query()) + [
      'services' => MedicalService::where('is_active', true)->orderBy('name')->get(),
      'weekPartialUrl' => url('/patient/book/week'),
      'baseUrl' => url('/patient/book'),
      'formAction' => '/appointments',
      'formMethod' => 'POST',
      'submitLabel' => 'Prenota',
    ]);
  }

  public function week(Request $request): View
  {
    $doctor = $this->availability->primaryDoctor();
    $selectedService = $request->integer('service_id')
      ? MedicalService::where('is_active', true)->find($request->integer('service_id'))
      : null;

    return view('patient.partials.booking-week-partial', $this->calendar->build($doctor, $selectedService, $request->except('slot_start')) + [
      'weekPartialUrl' => url('/patient/book/week'),
      'baseUrl' => url('/patient/book'),
    ]);
  }

  public function store(Request $request, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'slot_start' => ['required', 'date_format:Y-m-d\TH:i'],
      'service_id' => ['required', 'integer', 'exists:medical_services,id'],
      'notes' => ['nullable', 'string'],
    ]);

    $appointments->book(
      $request->user()->patientProfile,
      (string) $validated['slot_start'],
      (int) $validated['service_id'],
      $validated['notes'] ?? '',
    );

    return redirect('/patient/appointments')->with('status', 'Appuntamento confermato.');
  }
}
