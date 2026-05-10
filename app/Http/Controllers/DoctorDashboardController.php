<?php

namespace App\Http\Controllers;

use App\Exceptions\ScheduleAppointmentConflictsException;
use App\Http\Controllers\Concerns\ConfirmsScheduleAppointmentCancellations;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Services\AvailabilityService;
use App\Services\AppointmentService;
use App\Services\DoctorScheduleService;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
  use ConfirmsScheduleAppointmentCancellations;

  public function __construct(
    private readonly AvailabilityService $availability,
    private readonly DoctorScheduleService $schedule,
  )
  {
  }

  public function agenda(Request $request): View
  {
    return $this->viewAgenda($request);
  }

  public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'status' => ['required', 'string'],
    ]);

    $appointments->updateByDoctor($appointment, $validated['status']);

    return redirect('/doctor/agenda')->with('status', 'Stato appuntamento aggiornato.');
  }

  public function blockAvailability(Request $request): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $validated = $request->validate([
      'slot_start' => ['required', 'date_format:Y-m-d\TH:i'],
    ]);
    $slotStart = $this->availability->parseSlotStart($validated['slot_start']);

    if (! $slotStart || $slotStart->isPast()) {
      throw \Illuminate\Validation\ValidationException::withMessages([
        'slot_start' => 'Le disponibilita passate non possono essere bloccate.',
      ]);
    }

    $slotEnd = $slotStart->addMinutes(AvailabilityService::SLOT_STEP_MINUTES);

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

  public function updateClosure(Request $request, ScheduleClosure $closure): RedirectResponse
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
      $this->schedule->updateClosure(
        $doctor,
        $closure,
        $validated,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$validated['date'], [
        'title' => 'Conferma modifica chiusura',
        'message' => 'Questi appuntamenti verranno annullati per modificare la chiusura.',
        'action' => "/doctor/closures/{$closure->id}",
        'method' => 'PATCH',
        'payload' => $validated,
      ], $exception);
    }

    return redirect('/doctor/agenda?date='.$validated['date'])->with('status', 'Chiusura aggiornata.');
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

  public function updateSpecialOpening(Request $request, SpecialOpening $specialOpening): RedirectResponse
  {
    $doctor = $this->doctorFor($request);
    $validated = $request->validate([
      'date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'string', 'max:5'],
      'end_time' => ['required', 'string', 'max:5'],
      'note' => ['nullable', 'string', 'max:255'],
    ]);

    try {
      $this->schedule->updateSpecialOpening(
        $doctor,
        $specialOpening,
        $validated,
        $request->boolean(DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD),
      );
    } catch (ScheduleAppointmentConflictsException $exception) {
      return $this->redirectWithScheduleConfirmation('/doctor/agenda?date='.$validated['date'], [
        'title' => 'Conferma modifica apertura extra',
        'message' => 'Questi appuntamenti verranno annullati per modificare l\'apertura extra.',
        'action' => "/doctor/special-openings/{$specialOpening->id}",
        'method' => 'PATCH',
        'payload' => $validated,
      ], $exception);
    }

    return redirect('/doctor/agenda?date='.$validated['date'])->with('status', 'Apertura extra aggiornata.');
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

  private function viewAgenda(Request $request): View
  {
    $doctor = $this->doctorFor($request);
    $selectedDate = (string) $request->query('date', now()->toDateString());
    $selectedDay  = CarbonImmutable::parse($selectedDate)->startOfDay();
    $currentTime  = CarbonImmutable::now();

    $appointments = Appointment::withPortalRelations()
      ->where('doctor_profile_id', $doctor->id)
      ->whereDate('start_at', $selectedDate)
      ->where('status', '!=', Appointment::STATUS_CANCELLED)
      ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
      ->orderBy('start_at')
      ->get();

    $isPastDay = $selectedDay->lessThan($currentTime->startOfDay());
    $daySlots = $isPastDay ? collect() : $this->availability->agendaSlotsForDate($doctor, $selectedDay);
    $dayClosures = $isPastDay ? collect() : $this->availability->closuresForDate($doctor, $selectedDay);
    $upcomingScheduleEvents = $isPastDay ? collect() : $this->schedule->upcomingEvents($doctor, $selectedDay);

    $timelineItems = $this->buildTimelineItems($appointments, $daySlots, $dayClosures);

    $weekStartParam = $request->query('week_start');
    $weekStart = CarbonImmutable::parse(
      $weekStartParam ?? $selectedDay->toDateString()
    )->startOfWeek(CarbonImmutable::MONDAY);

    $daysWithSlots = collect(range(0, 6))
      ->map(fn ($i) => $weekStart->addDays($i))
      ->filter(fn (CarbonImmutable $date) => ! $date->lessThan($currentTime->startOfDay()))
      ->filter(fn (CarbonImmutable $date) => $this->availability->agendaSlotsForDate($doctor, $date)->isNotEmpty())
      ->map(fn (CarbonImmutable $date) => $date->toDateString())
      ->all();

    $weekDays = collect(range(0, 6))->map(fn ($i) => [
      'date'     => $weekStart->addDays($i),
      'hasSlots' => in_array($weekStart->addDays($i)->toDateString(), $daysWithSlots),
    ]);

    return view('doctor.agenda', [
      'date'                => $selectedDate,
      'appointments'        => $appointments,
      'daySlots'            => $daySlots,
      'dayClosures'         => $dayClosures,
      'upcomingScheduleEvents' => $upcomingScheduleEvents,
      'agendaRows'          => $this->buildAgendaRows($selectedDay, $timelineItems, $currentTime),
      'currentTime'         => $currentTime,
      'isSelectedToday'     => $selectedDay->toDateString() === $currentTime->toDateString(),
      'fatturato'           => $appointments->sum(fn ($a) => $a->service?->price ?? 0),
      'weekDays'            => $weekDays,
      'weekStart'           => $weekStart,
      'previousWeekStart'   => $weekStart->subWeek(),
      'nextWeekStart'       => $weekStart->addWeek(),
    ]);
  }

  private function buildTimelineItems($appointments, $daySlots, $dayClosures)
  {
    $slotItems = $daySlots->map(fn (VirtualAvailabilitySlot $slot): array => [
        'type' => 'slot',
        'state' => 'free',
        'start_at' => $slot->start_at,
        'end_at' => $slot->end_at,
        'slot' => $slot,
        'appointment' => null,
        'closure' => null,
      ]);

    $closureItems = $dayClosures->map(fn (array $closure): array => $this->buildClosureTimelineItem($closure));

    $appointmentItems = $appointments
      ->map(fn (Appointment $appointment) => [
        'type' => 'appointment',
        'state' => 'booked',
        'start_at' => $appointment->start_at,
        'end_at' => $appointment->end_at,
        'slot' => null,
        'appointment' => $appointment,
        'closure' => null,
      ]);

    return $slotItems
      ->concat($closureItems)
      ->concat($appointmentItems)
      ->sortBy(fn (array $item) => $item['start_at']->getTimestamp())
      ->values();
  }

  private function buildClosureTimelineItem(array $closure): array
  {
    $start = $closure['start_at'];
    $end = $closure['end_at'];
    $durationSeconds = $end->greaterThan($start)
      ? $start->diffInSeconds($end)
      : AvailabilityService::SLOT_STEP_MINUTES * 60;
    $spanRows = max(1, (int) ceil($durationSeconds / (AvailabilityService::SLOT_STEP_MINUTES * 60)));

    return [
      'type' => 'closure',
      'state' => 'blocked',
      'start_at' => $start,
      'end_at' => $end,
      'slot' => null,
      'appointment' => null,
      'closure' => $closure['closure'],
      'closure_start_at' => $start,
      'closure_end_at' => $end,
      'span_rows' => $spanRows,
    ];
  }

  private function buildAgendaRows(CarbonImmutable $selectedDay, $timelineItems, CarbonImmutable $currentTime)
  {
    $itemsByRow = collect();
    foreach ($timelineItems as $item) {
      $minutesFromStart = max(0, min(
        1439,
        $selectedDay->diffInMinutes($item['start_at'], false)
      ));
      $rowIndex = intdiv((int) $minutesFromStart, 30);
      $rowItems = $itemsByRow->get($rowIndex, collect());
      $rowItems->push($item);
      $itemsByRow->put($rowIndex, $rowItems);
    }

    $isToday = $selectedDay->toDateString() === $currentTime->toDateString();

    return collect(range(0, 47))->map(function (int $rowIndex) use ($selectedDay, $itemsByRow, $currentTime, $isToday) {
      $start = $selectedDay->addMinutes($rowIndex * 30);
      $end = $start->addMinutes(30);
      $isCurrent = $isToday && $currentTime->greaterThanOrEqualTo($start) && $currentTime->lessThan($end);
      $nowPosition = null;

      if ($isCurrent) {
        $minutesIntoRow = $start->diffInMinutes($currentTime);
        $nowPosition = min(100, max(0, ($minutesIntoRow / 30) * 100));
      }

      return [
        'label' => $start->format('H:i'),
        'start_at' => $start,
        'end_at' => $end,
        'items' => $itemsByRow->get($rowIndex, collect()),
        'is_past' => $isToday && $end->lessThanOrEqualTo($currentTime),
        'is_current' => $isCurrent,
        'now_position' => $nowPosition,
      ];
    });
  }

  private function doctorFor(Request $request): DoctorProfile
  {
    $doctor = $request->user()->doctorProfile;
    abort_unless($doctor, 404);

    return $doctor;
  }
}
