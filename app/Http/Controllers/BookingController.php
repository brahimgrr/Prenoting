<?php

namespace App\Http\Controllers;

use App\Services\AppointmentService;
use App\Services\PatientBookingWizardViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
  public function __construct(
    private readonly PatientBookingWizardViewData $bookingWizard,
  )
  {
  }

  public function show(Request $request): View
  {
    $viewData = $this->bookingWizard->booking($request);

    if ($request->ajax()) {
      return view('patient.partials.booking-wizard', $viewData);
    }

    return view('patient.booking', $viewData);
  }

  public function week(Request $request): View
  {
    return view('patient.partials.booking-week-partial', array_replace($this->bookingWizard->booking($request), [
      'selectedSlot' => null,
    ]));
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
