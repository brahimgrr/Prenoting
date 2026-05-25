<?php

namespace App\Http\Controllers;

use App\Exceptions\ScheduleAppointmentConflictsException;
use App\Http\Controllers\Concerns\ConfirmsScheduleAppointmentCancellations;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Services\AppointmentService;
use App\Services\DoctorAgendaViewService;
use App\Services\DoctorScheduleService;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
  use ConfirmsScheduleAppointmentCancellations;

  public function __construct(
    private readonly DoctorScheduleService $schedule,
    private readonly DoctorAgendaViewService $agendaView,
  ) {
  }

  public function agenda(Request $request): View
  {
    return view('doctor.agenda', $this->agendaView->build($this->doctorFor($request), $request->query()));
  }

  public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'status' => ['required', 'string'],
    ]);

    $appointments->updateByDoctor($appointment, $validated['status']);

    return redirect('/doctor/agenda')->with('status', 'Stato appuntamento aggiornato.');
  }

  public function cancelAppointment(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    abort_unless($appointment->doctor_profile_id === $doctor->id, 404);

    $validated = $request->validate([
      'cancellation_reason' => ['nullable', 'string'],
    ]);

    $appointments->cancelByDoctor($appointment, $validated['cancellation_reason'] ?? '', $request->user());

    return redirect('/doctor/agenda?date='.$appointment->start_at->toDateString())
      ->with('status', 'Appuntamento annullato.');
  }

  public function blockAvailability(Request $request): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $validated = $request->validate([
      'slot_start' => ['required', 'date_format:Y-m-d\TH:i'],
    ]);
    $slotStart = ScheduleTime::parseSlotStart($validated['slot_start']);

    if (! $slotStart || $slotStart->isPast()) {
      throw ValidationException::withMessages([
        'slot_start' => 'Le disponibilita passate non possono essere bloccate.',
      ]);
    }

    $slotEnd = $slotStart->addMinutes(ScheduleTime::GRID_MINUTES);

    $payload = [
      'date' => $slotStart->toDateString(),
      'start_time' => $slotStart->format('H:i'),
      'end_time' => $slotEnd->toDateString() === $slotStart->toDateString() ? $slotEnd->format('H:i') : '24:00',
      'reason' => 'Disponibilita bloccata',
    ];

    try {
      $this->schedule->createClosure(
        $doctor,
        $payload,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$payload['date'], [
        'title' => 'Conferma chiusura',
        'message' => 'Questi appuntamenti verranno annullati per bloccare la disponibilita.',
        'action' => '/doctor/availability/block',
        'method' => 'POST',
        'payload' => ['slot_start' => $validated['slot_start']],
      ], $exception);
    }

    return redirect()->back()->with('status', 'Disponibilita bloccata.');
  }

  public function storeClosure(Request $request): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $validated = $request->validate([
      'date' => ['required', 'date_format:Y-m-d'],
      'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
      'all_day' => ['nullable', 'string'],
      'start_time' => ['nullable', 'string', 'max:5'],
      'end_time' => ['nullable', 'string', 'max:5'],
      'reason' => ['nullable', 'string', 'max:255'],
    ]);

    try {
      $this->schedule->createClosure(
        $doctor,
        $validated,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$validated['date'], [
        'title' => 'Conferma chiusura',
        'message' => 'Questi appuntamenti verranno annullati per creare la chiusura.',
        'action' => '/doctor/closures',
        'method' => 'POST',
        'payload' => $validated,
      ], $exception);
    }

    return redirect('/doctor/agenda?date='.$validated['date'])->with('status', 'Chiusura creata.');
  }

  public function destroyClosure(Request $request, ScheduleClosure $closure): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $date = CarbonImmutable::parse($closure->date)->toDateString();

    try {
      $this->schedule->deleteClosure(
        $doctor,
        $closure,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$date, [
        'title' => 'Conferma rimozione chiusura',
        'message' => 'Questi appuntamenti verranno annullati per rimuovere la chiusura.',
        'action' => "/doctor/closures/{$closure->id}",
        'method' => 'DELETE',
        'payload' => [],
      ], $exception);
    }

    return redirect('/doctor/agenda?date='.$date)->with('status', 'Chiusura rimossa.');
  }

  public function storeSpecialOpening(Request $request): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $validated = $request->validate([
      'date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'string', 'max:5'],
      'end_time' => ['required', 'string', 'max:5'],
      'note' => ['nullable', 'string', 'max:255'],
    ]);

    $this->schedule->createSpecialOpening($doctor, $validated);

    return redirect('/doctor/agenda?date='.$validated['date'])->with('status', 'Apertura extra creata.');
  }

  public function destroySpecialOpening(Request $request, SpecialOpening $specialOpening): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $date = CarbonImmutable::parse($specialOpening->date)->toDateString();

    try {
      $this->schedule->deleteSpecialOpening(
        $doctor,
        $specialOpening,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$date, [
        'title' => 'Conferma eliminazione apertura extra',
        'message' => 'Questi appuntamenti verranno annullati per eliminare l\'apertura extra.',
        'action' => "/doctor/special-openings/{$specialOpening->id}",
        'method' => 'DELETE',
        'payload' => [],
      ], $exception);
    }

    return redirect('/doctor/agenda?date='.$date)->with('status', 'Apertura extra rimossa.');
  }

  private function doctorFor(Request $request): DoctorProfile
  {
    $doctor = $request->user()->doctorProfile;
    abort_unless($doctor, 404);

    return $doctor;
  }
}
