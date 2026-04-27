<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
  public function today(Request $request): View
  {
    return $this->viewSchedule($request, 'today');
  }

  public function schedule(Request $request): View
  {
    return $this->viewSchedule($request, 'schedule');
  }

  public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    abort_unless($appointment->doctor_id === $request->user()->doctorProfile->id, 404);

    $validated = $request->validate([
      'status' => ['required', 'string'],
    ]);

    $appointments->updateByDoctor($appointment, $validated['status'], $request->user());

    return redirect('/doctor/schedule')->with('status', 'Stato appuntamento aggiornato.');
  }

  public function storeAvailability(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'clinic_id' => ['required', 'integer', 'exists:clinic_locations,id'],
      'date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_time' => ['required', 'date_format:H:i'],
    ]);

    $doctor = $request->user()->doctorProfile;
    $startAt = "{$validated['date']} {$validated['start_time']}:00";
    $endAt = "{$validated['date']} {$validated['end_time']}:00";

    $start = CarbonImmutable::parse($startAt);
    $end = CarbonImmutable::parse($endAt);

    if ($start->isPast()) {
      throw ValidationException::withMessages([
        'start_time' => 'La disponibilita deve essere futura.',
      ]);
    }

    if ($end->lessThanOrEqualTo($start)) {
      throw ValidationException::withMessages([
        'end_time' => "L'orario di fine deve essere successivo all'inizio.",
      ]);
    }

    $overlaps = AvailabilitySlot::query()
      ->where('doctor_id', $doctor->id)
      ->where('start_at', '<', $end)
      ->where('end_at', '>', $start)
      ->exists();

    if ($overlaps) {
      throw ValidationException::withMessages([
        'start_time' => 'Esiste gia una disponibilita sovrapposta.',
      ]);
    }

    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $validated['clinic_id'],
      'start_at' => $start,
      'end_at' => $end,
    ]);

    return redirect('/doctor/schedule')->with('status', 'Disponibilita aggiunta.');
  }

  public function blockAvailability(Request $request, AvailabilitySlot $slot): RedirectResponse
  {
    $this->authorizeDoctorSlot($request, $slot);

    if ($slot->start_at->isPast()) {
      throw ValidationException::withMessages([
        'slot' => 'Le disponibilita passate non possono essere bloccate.',
      ]);
    }

    $slot->forceFill(['is_blocked' => true])->save();

    return redirect('/doctor/schedule')->with('status', 'Disponibilita bloccata.');
  }

  public function unblockAvailability(Request $request, AvailabilitySlot $slot): RedirectResponse
  {
    $this->authorizeDoctorSlot($request, $slot);

    if ($slot->start_at->isPast()) {
      throw ValidationException::withMessages([
        'slot' => 'Le disponibilita passate non possono essere riaperte.',
      ]);
    }

    $slot->forceFill(['is_blocked' => false])->save();

    return redirect('/doctor/schedule')->with('status', 'Disponibilita riaperta.');
  }

  private function viewSchedule(Request $request, string $mode): View
  {
    $date = $request->query('date');
    $effectiveDate = $mode === 'today' ? now()->toDateString() : $date;
    $appointments = Appointment::withPortalRelations()
      ->where('doctor_id', $request->user()->doctorProfile->id)
      ->when($effectiveDate, fn ($query, $selectedDate) => $query->whereDate('start_at', $selectedDate))
      ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
      ->orderBy('start_at')
      ->get();

    return view('doctor.dashboard', [
      'mode' => $mode,
      'date' => $date ?? now()->toDateString(),
      'appointments' => $appointments,
      'visibleAppointments' => $mode === 'today' ? $appointments->take(4) : $appointments,
      'availabilitySlots' => AvailabilitySlot::with('clinic')
        ->where('doctor_id', $request->user()->doctorProfile->id)
        ->where('start_at', '>=', now())
        ->orderBy('start_at')
        ->get()
        ->groupBy(fn (AvailabilitySlot $slot) => $slot->start_at->toDateString()),
    ]);
  }

  private function authorizeDoctorSlot(Request $request, AvailabilitySlot $slot): void
  {
    abort_unless($slot->doctor_id === $request->user()->doctorProfile->id, 404);
  }
}
