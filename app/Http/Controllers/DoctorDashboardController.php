<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
  public function schedule(Request $request): View
  {
    return $this->viewSchedule($request);
  }

  public function availability(Request $request): View
  {
    return $this->viewAvailability($request);
  }

  public function agendaV2(Request $request): View
  {
    return $this->viewAgendaV2($request);
  }

  public function updateStatus(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
  {
    $validated = $request->validate([
      'status' => ['required', 'string'],
    ]);

    $appointments->updateByDoctor($appointment, $validated['status'], $request->user());

    return redirect('/doctor/schedule')->with('status', 'Stato appuntamento aggiornato.');
  }

  public function storeAvailability(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_time' => ['required', 'date_format:H:i'],
    ]);

    $start = CarbonImmutable::parse("{$validated['date']} {$validated['start_time']}:00");
    $end = CarbonImmutable::parse("{$validated['date']} {$validated['end_time']}:00");

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

    if ($this->slotOverlaps($start, $end)) {
      throw ValidationException::withMessages([
        'start_time' => 'Esiste gia una disponibilita sovrapposta.',
      ]);
    }

    AvailabilitySlot::create([
      'start_at' => $start,
      'end_at' => $end,
    ]);

    return redirect('/doctor/availability')->with('status', 'Disponibilita aggiunta.');
  }

  public function previewAvailability(Request $request): View
  {
    $validated = $this->validatedBatchAvailability($request);
    $preview = $this->buildAvailabilityPreview($validated);

    if ($request->query('source') === 'agendav2') {
      return $this->viewAgendaV2($request, $preview);
    }

    return $this->viewAvailability($request, $preview);
  }

  public function storeAvailabilityBatch(Request $request): RedirectResponse
  {
    $validated = $this->validatedBatchAvailability($request);
    $preview = $this->buildAvailabilityPreview($validated);

    if ($preview['creatable']->isEmpty()) {
      throw ValidationException::withMessages([
        'availability' => 'Nessuno slot disponibile da creare: prova un intervallo futuro senza sovrapposizioni.',
      ]);
    }

    $created = 0;
    DB::transaction(function () use ($preview, &$created): void {
      foreach ($preview['creatable'] as $candidate) {
        if ($this->slotOverlaps($candidate['start_at'], $candidate['end_at'])) {
          continue;
        }

        AvailabilitySlot::create([
          'start_at' => $candidate['start_at'],
          'end_at' => $candidate['end_at'],
        ]);
        $created++;
      }
    });

    if ($created === 0) {
      throw ValidationException::withMessages([
        'availability' => 'Gli slot selezionati risultano gia occupati. Ricalcola una nuova anteprima.',
      ]);
    }

    $redirectTo = $request->input('_source') === 'agendav2'
      ? '/doctor/agendav2'
      : '/doctor/availability';

    return redirect($redirectTo)->with('status', "{$created} slot disponibilita creati.");
  }

  public function blockAvailability(Request $request, AvailabilitySlot $slot): RedirectResponse
  {
    $this->authorizeDoctorArea($request);

    if ($slot->start_at->isPast()) {
      throw ValidationException::withMessages([
        'slot' => 'Le disponibilita passate non possono essere bloccate.',
      ]);
    }

    $slot->forceFill(['is_blocked' => true])->save();

    return redirect()->back()->with('status', 'Disponibilita bloccata.');
  }

  public function unblockAvailability(Request $request, AvailabilitySlot $slot): RedirectResponse
  {
    $this->authorizeDoctorArea($request);

    if ($slot->start_at->isPast()) {
      throw ValidationException::withMessages([
        'slot' => 'Le disponibilita passate non possono essere riaperte.',
      ]);
    }

    $slot->forceFill(['is_blocked' => false])->save();

    return redirect()->back()->with('status', 'Disponibilita riaperta.');
  }

  private function viewSchedule(Request $request): View
  {
    $selectedDate = (string) $request->query('date', now()->toDateString());
    $selectedDay = CarbonImmutable::parse($selectedDate)->startOfDay();
    $currentTime = CarbonImmutable::now();
    $appointments = Appointment::withPortalRelations()
      ->whereDate('start_at', $selectedDate)
      ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
      ->orderBy('start_at')
      ->get();
    $daySlots = AvailabilitySlot::query()
      ->whereDate('start_at', $selectedDate)
      ->orderBy('start_at')
      ->get();
    $timelineItems = $this->buildTimelineItems($appointments, $daySlots);

    return view('doctor.dashboard', [
      'date' => $selectedDate,
      'appointments' => $appointments,
      'daySlots' => $daySlots,
      'timelineItems' => $timelineItems,
      'agendaRows' => $this->buildAgendaRows($selectedDay, $timelineItems, $currentTime),
      'currentTime' => $currentTime,
      'isSelectedToday' => $selectedDay->toDateString() === $currentTime->toDateString(),
    ]);
  }

  private function viewAvailability(Request $request, ?array $availabilityPreview = null): View
  {
    $batchForm = $availabilityPreview['input'] ?? [
      'start_date'          => CarbonImmutable::now()->toDateString(),
      'end_date'            => CarbonImmutable::now()->addWeeks(2)->toDateString(),
      'weekdays'            => [1, 2, 3, 4, 5],
      'start_time'          => '09:00',
      'end_time'            => '12:00',
      'slot_duration'       => 30,
      'lunch_break_enabled' => false,
      'lunch_break_start'   => '13:00',
      'lunch_break_end'     => '14:00',
    ];

    return view('doctor.availability', [
      'availabilityPreview' => $availabilityPreview,
      'batchForm'           => $batchForm,
      'availabilitySlots'   => AvailabilitySlot::query()
        ->where('start_at', '>=', now())
        ->with(['appointments.patient.user', 'appointments.service'])
        ->orderBy('start_at')
        ->get()
        ->groupBy(fn (AvailabilitySlot $slot) => $slot->start_at->toDateString()),
    ]);
  }

  private function viewAgendaV2(Request $request, ?array $availabilityPreview = null): View
  {
    $selectedDate = (string) $request->query('date', now()->toDateString());
    $selectedDay  = CarbonImmutable::parse($selectedDate)->startOfDay();
    $currentTime  = CarbonImmutable::now();

    $appointments = Appointment::withPortalRelations()
      ->whereDate('start_at', $selectedDate)
      ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
      ->orderBy('start_at')
      ->get();

    $daySlots = AvailabilitySlot::query()
      ->whereDate('start_at', $selectedDate)
      ->orderBy('start_at')
      ->get();

    $timelineItems = $this->buildTimelineItems($appointments, $daySlots);

    $weekStartParam = $request->query('week_start');
    $weekStart = CarbonImmutable::parse(
      $weekStartParam ?? $selectedDay->toDateString()
    )->startOfWeek(CarbonImmutable::MONDAY);
    $weekEnd = $weekStart->addDays(4);

    $daysWithSlots = AvailabilitySlot::query()
      ->whereBetween('start_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
      ->selectRaw('DATE(start_at) as slot_date')
      ->distinct()
      ->pluck('slot_date')
      ->all();

    $weekDays = collect(range(0, 4))->map(fn ($i) => [
      'date'     => $weekStart->addDays($i),
      'hasSlots' => in_array($weekStart->addDays($i)->toDateString(), $daysWithSlots),
    ]);

    $batchForm = $availabilityPreview['input'] ?? [
      'start_date'          => CarbonImmutable::now()->toDateString(),
      'end_date'            => CarbonImmutable::now()->addWeeks(2)->toDateString(),
      'weekdays'            => [1, 2, 3, 4, 5],
      'start_time'          => '09:00',
      'end_time'            => '12:00',
      'slot_duration'       => 30,
      'lunch_break_enabled' => false,
      'lunch_break_start'   => '13:00',
      'lunch_break_end'     => '14:00',
    ];

    return view('doctor.agendav2', [
      'date'                => $selectedDate,
      'appointments'        => $appointments,
      'daySlots'            => $daySlots,
      'agendaRows'          => $this->buildAgendaRows($selectedDay, $timelineItems, $currentTime),
      'currentTime'         => $currentTime,
      'isSelectedToday'     => $selectedDay->toDateString() === $currentTime->toDateString(),
      'fatturato'           => $appointments->sum(fn ($a) => $a->service?->price ?? 0),
      'weekDays'            => $weekDays,
      'weekStart'           => $weekStart,
      'previousWeekStart'   => $weekStart->subWeek(),
      'nextWeekStart'       => $weekStart->addWeek(),
      'availabilityPreview' => $availabilityPreview,
      'batchForm'           => $batchForm,
    ]);
  }

  private function buildTimelineItems($appointments, $daySlots)
  {
    $appointmentsBySlot = $appointments
      ->filter(fn (Appointment $appointment) => $appointment->slot_id !== null)
      ->keyBy('slot_id');
    $coveredAppointmentIds = collect();

    $slotItems = $daySlots->map(function (AvailabilitySlot $slot) use ($appointmentsBySlot, $coveredAppointmentIds) {
      $appointment = $appointmentsBySlot->get($slot->id);

      if ($appointment) {
        $coveredAppointmentIds->push($appointment->id);

        return [
          'type' => 'appointment',
          'state' => 'booked',
          'start_at' => $appointment->start_at,
          'end_at' => $appointment->end_at,
          'slot' => $slot,
          'appointment' => $appointment,
        ];
      }

      return [
        'type' => 'slot',
        'state' => $this->slotTimelineState($slot),
        'start_at' => $slot->start_at,
        'end_at' => $slot->end_at,
        'slot' => $slot,
        'appointment' => null,
      ];
    });

    $fallbackAppointmentItems = $appointments
      ->reject(fn (Appointment $appointment) => $coveredAppointmentIds->contains($appointment->id))
      ->map(fn (Appointment $appointment) => [
        'type' => 'appointment',
        'state' => 'booked',
        'start_at' => $appointment->start_at,
        'end_at' => $appointment->end_at,
        'slot' => $appointment->slot,
        'appointment' => $appointment,
      ]);

    return $slotItems
      ->concat($fallbackAppointmentItems)
      ->sortBy(fn (array $item) => $item['start_at']->getTimestamp())
      ->values();
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

  private function slotTimelineState(AvailabilitySlot $slot): string
  {
    if ($slot->is_blocked) {
      return 'blocked';
    }

    if ($slot->is_booked) {
      return 'booked';
    }

    return 'free';
  }

  private function authorizeDoctorArea(Request $request): void
  {
    abort_unless($request->user()->doctorProfile, 404);
  }

  private function validatedBatchAvailability(Request $request): array
  {
    $validated = $request->validate([
      'start_date'          => ['required', 'date_format:Y-m-d'],
      'end_date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
      'weekdays'            => ['required', 'array', 'min:1'],
      'weekdays.*'          => ['integer', 'between:1,5'],
      'start_time'          => ['required', 'date_format:H:i'],
      'end_time'            => ['required', 'date_format:H:i'],
      'slot_duration'       => ['required', 'integer', 'in:15,20,30,45,60'],
      'lunch_break_enabled' => ['nullable', 'string'],
      'lunch_break_start'   => ['nullable', 'required_if:lunch_break_enabled,1', 'date_format:H:i'],
      'lunch_break_end'     => ['nullable', 'required_if:lunch_break_enabled,1', 'date_format:H:i'],
    ]);

    $startTime = CarbonImmutable::parse("2000-01-01 {$validated['start_time']}:00");
    $endTime   = CarbonImmutable::parse("2000-01-01 {$validated['end_time']}:00");
    if ($endTime->lessThanOrEqualTo($startTime)) {
      throw ValidationException::withMessages([
        'end_time' => "L'orario di fine deve essere successivo all'inizio.",
      ]);
    }

    $validated['lunch_break_enabled'] = ($validated['lunch_break_enabled'] ?? null) === '1';

    if ($validated['lunch_break_enabled']) {
      $lbStart = CarbonImmutable::parse("2000-01-01 {$validated['lunch_break_start']}:00");
      $lbEnd   = CarbonImmutable::parse("2000-01-01 {$validated['lunch_break_end']}:00");
      if ($lbEnd->lessThanOrEqualTo($lbStart)) {
        throw ValidationException::withMessages([
          'lunch_break_end' => "La fine della pausa deve essere successiva all'inizio.",
        ]);
      }
    }

    $validated['weekdays'] = collect($validated['weekdays'])
      ->map(fn ($w) => (int) $w)
      ->unique()->sort()->values()->all();
    $validated['slot_duration'] = (int) $validated['slot_duration'];

    return $validated;
  }

  private function buildAvailabilityPreview(array $input): array
  {
    $creatable = collect();
    $skipped = collect();
    $startDate = CarbonImmutable::parse($input['start_date'])->startOfDay();
    $endDate = CarbonImmutable::parse($input['end_date'])->startOfDay();
    [$startHour, $startMinute] = array_map('intval', explode(':', $input['start_time']));
    [$endHour, $endMinute] = array_map('intval', explode(':', $input['end_time']));

    $lunchBreakEnabled = $input['lunch_break_enabled'] ?? false;
    $lunchStart = $lunchBreakEnabled && isset($input['lunch_break_start'])
      ? CarbonImmutable::parse("2000-01-01 {$input['lunch_break_start']}:00")
      : null;
    $lunchEnd = $lunchBreakEnabled && isset($input['lunch_break_end'])
      ? CarbonImmutable::parse("2000-01-01 {$input['lunch_break_end']}:00")
      : null;

    for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
      if (! in_array($date->dayOfWeekIso, $input['weekdays'], true)) {
        continue;
      }

      $windowStart = $date->setTime($startHour, $startMinute);
      $windowEnd = $date->setTime($endHour, $endMinute);
      for ($slotStart = $windowStart; $slotStart->addMinutes($input['slot_duration'])->lessThanOrEqualTo($windowEnd); $slotStart = $slotStart->addMinutes($input['slot_duration'])) {
        $slotEnd = $slotStart->addMinutes($input['slot_duration']);
        $candidate = [
          'start_at' => $slotStart,
          'end_at' => $slotEnd,
        ];

        if ($lunchStart && $lunchEnd) {
          $slotStartTime = CarbonImmutable::parse("2000-01-01 {$slotStart->format('H:i')}:00");
          $slotEndTime   = CarbonImmutable::parse("2000-01-01 {$slotEnd->format('H:i')}:00");
          if ($slotStartTime->lessThan($lunchEnd) && $slotEndTime->greaterThan($lunchStart)) {
            $skipped->push($candidate + ['reason' => 'pausa pranzo']);
            continue;
          }
        }

        if ($slotStart->isPast()) {
          $skipped->push($candidate + ['reason' => 'passato']);
          continue;
        }

        if ($this->slotOverlaps($slotStart, $slotEnd)) {
          $skipped->push($candidate + ['reason' => 'sovrapposto']);
          continue;
        }

        $creatable->push($candidate);
      }
    }

    return [
      'input' => $input,
      'creatable' => $creatable,
      'skipped' => $skipped,
      'weekdayLabels' => collect($input['weekdays'])
        ->map(fn (int $weekday) => $this->weekdayLabel($weekday))
        ->implode(', '),
    ];
  }

  private function slotOverlaps(CarbonImmutable $start, CarbonImmutable $end): bool
  {
    return AvailabilitySlot::query()
      ->where('start_at', '<', $end)
      ->where('end_at', '>', $start)
      ->exists();
  }

  private function weekdayLabel(int $weekday): string
  {
    return [
      1 => 'Lun',
      2 => 'Mar',
      3 => 'Mer',
      4 => 'Gio',
      5 => 'Ven',
    ][$weekday] ?? (string) $weekday;
  }
}
