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

    return redirect('/doctor/schedule')->with('status', 'Disponibilita aggiunta.');
  }

  public function previewAvailability(Request $request): View
  {
    $validated = $this->validatedBatchAvailability($request);
    $preview = $this->buildAvailabilityPreview($validated);

    return $this->viewSchedule($request, 'schedule', $preview);
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

    return redirect('/doctor/schedule')->with('status', "{$created} slot disponibilita creati.");
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

    return redirect('/doctor/schedule')->with('status', 'Disponibilita bloccata.');
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

    return redirect('/doctor/schedule')->with('status', 'Disponibilita riaperta.');
  }

  private function viewSchedule(Request $request, string $mode, ?array $availabilityPreview = null): View
  {
    $date = $request->query('date');
    $effectiveDate = $mode === 'today' ? now()->toDateString() : $date;
    $batchForm = $availabilityPreview['input'] ?? [
      'start_date' => CarbonImmutable::now()->toDateString(),
      'end_date' => CarbonImmutable::now()->addWeeks(2)->toDateString(),
      'weekdays' => [1, 2, 3, 4, 5],
      'start_time' => '09:00',
      'end_time' => '12:00',
      'slot_duration' => 30,
    ];
    $appointments = Appointment::withPortalRelations()
      ->when($effectiveDate, fn ($query, $selectedDate) => $query->whereDate('start_at', $selectedDate))
      ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
      ->orderBy('start_at')
      ->get();

    return view('doctor.dashboard', [
      'mode' => $mode,
      'date' => $date ?? now()->toDateString(),
      'appointments' => $appointments,
      'visibleAppointments' => $mode === 'today' ? $appointments->take(4) : $appointments,
      'availabilityPreview' => $availabilityPreview,
      'batchForm' => $batchForm,
      'availabilitySlots' => AvailabilitySlot::query()
        ->where('start_at', '>=', now())
        ->orderBy('start_at')
        ->get()
        ->groupBy(fn (AvailabilitySlot $slot) => $slot->start_at->toDateString()),
    ]);
  }

  private function authorizeDoctorArea(Request $request): void
  {
    abort_unless($request->user()->doctorProfile, 404);
  }

  private function validatedBatchAvailability(Request $request): array
  {
    $validated = $request->validate([
      'start_date' => ['required', 'date_format:Y-m-d'],
      'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
      'weekdays' => ['required', 'array', 'min:1'],
      'weekdays.*' => ['integer', 'between:1,5'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_time' => ['required', 'date_format:H:i'],
      'slot_duration' => ['required', 'integer', 'in:15,20,30,45,60'],
    ]);

    $startTime = CarbonImmutable::parse("2000-01-01 {$validated['start_time']}:00");
    $endTime = CarbonImmutable::parse("2000-01-01 {$validated['end_time']}:00");
    if ($endTime->lessThanOrEqualTo($startTime)) {
      throw ValidationException::withMessages([
        'end_time' => "L'orario di fine deve essere successivo all'inizio.",
      ]);
    }

    $validated['weekdays'] = collect($validated['weekdays'])
      ->map(fn ($weekday) => (int) $weekday)
      ->unique()
      ->sort()
      ->values()
      ->all();
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
