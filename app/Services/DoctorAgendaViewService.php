<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Support\ScheduleTime;
use App\Support\VirtualAvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DoctorAgendaViewService
{
    public function __construct(
        private readonly AvailabilityService   $availability,
        private readonly ScheduleWindowService $windows,
    )
    {
    }

    public function build(DoctorProfile $doctor, array $query): array
    {
        $selectedDate = $this->stringQuery($query, 'date') ?: now()->toDateString();
        $selectedDay = CarbonImmutable::parse($selectedDate)->startOfDay();
        $currentTime = CarbonImmutable::now();

        $appointments = Appointment::withPortalRelations()
            ->forDoctor($doctor)
            ->whereDate('start_at', $selectedDate)
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->when($this->stringQuery($query, 'status'), fn($q, $status) => $q->where('status', $status))
            ->orderBy('start_at')
            ->get();

        $isPastDay = $selectedDay->lessThan($currentTime->startOfDay());
        $daySlots = $isPastDay ? collect() : $this->availability->agendaSlotsForDate($doctor, $selectedDay);
        $dayClosures = $isPastDay ? collect() : $this->windows->closuresForDate($doctor, $selectedDay);
        $upcomingScheduleEvents = $this->upcomingEvents($doctor, $currentTime->startOfDay());
        $timelineItems = $this->timelineItems($appointments, $daySlots, $dayClosures);
        $weekStart = CarbonImmutable::parse($this->stringQuery($query, 'week_start') ?: $selectedDay->toDateString())
            ->startOfWeek(CarbonImmutable::MONDAY);

        return [
            'date' => $selectedDate,
            'appointments' => $appointments,
            'daySlots' => $daySlots,
            'dayClosures' => $dayClosures,
            'upcomingScheduleEvents' => $upcomingScheduleEvents,
            'agendaRows' => $this->agendaRows($selectedDay, $timelineItems, $currentTime),
            'currentTime' => $currentTime,
            'isSelectedToday' => $selectedDay->toDateString() === $currentTime->toDateString(),
            'fatturato' => $appointments->sum(fn($appointment) => $appointment->service?->price ?? 0),
            'weekDays' => $this->weekDays($doctor, $weekStart, $currentTime),
            'weekStart' => $weekStart,
            'previousWeekStart' => $weekStart->subWeek(),
            'nextWeekStart' => $weekStart->addWeek(),
        ];
    }

    private function stringQuery(array $query, string $key): string
    {
        $value = $query[$key] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }

    private function upcomingEvents(DoctorProfile $doctor, CarbonImmutable $from, int $limit = 8): Collection
    {
        $closures = $doctor->closures()
            ->whereDate('date', '>=', $from->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->map(fn(ScheduleClosure $closure): array => [
                'type' => 'closure',
                'date' => CarbonImmutable::parse($closure->date),
                'start_time' => $closure->start_time ? substr((string)$closure->start_time, 0, 5) : null,
                'end_time' => $closure->end_time ? substr((string)$closure->end_time, 0, 5) : null,
                'title' => $closure->reason ?: 'Chiusura',
                'model' => $closure,
            ]);

        $specialOpenings = $doctor->specialOpenings()
            ->whereDate('date', '>=', $from->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->map(fn(SpecialOpening $opening): array => [
                'type' => 'special_opening',
                'date' => CarbonImmutable::parse($opening->date),
                'start_time' => substr((string)$opening->start_time, 0, 5),
                'end_time' => substr((string)$opening->end_time, 0, 5),
                'title' => $opening->note ?: 'Apertura extra',
                'model' => $opening,
            ]);

        return $closures
            ->concat($specialOpenings)
            ->sortBy(fn(array $event): string => $event['date']->toDateString() . ' ' . ($event['start_time'] ?? '00:00'))
            ->take($limit)
            ->values();
    }

    private function timelineItems(Collection $appointments, Collection $daySlots, Collection $dayClosures): Collection
    {
        $slotItems = $daySlots->map(fn(VirtualAvailabilitySlot $slot): array => [
            'type' => 'slot',
            'state' => 'free',
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'slot' => $slot,
            'appointment' => null,
            'closure' => null,
        ]);

        $closureItems = $dayClosures->map(fn(array $closure): array => $this->closureTimelineItem($closure));

        $appointmentItems = $appointments->map(fn(Appointment $appointment): array => [
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
            ->sortBy(fn(array $item): int => $item['start_at']->getTimestamp())
            ->values();
    }

    private function closureTimelineItem(array $closure): array
    {
        $start = $closure['start_at'];
        $end = $closure['end_at'];
        $durationSeconds = $end->greaterThan($start)
            ? $start->diffInSeconds($end)
            : ScheduleTime::GRID_MINUTES * 60;
        $spanRows = max(1, (int)ceil($durationSeconds / (ScheduleTime::GRID_MINUTES * 60)));

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

    private function agendaRows(CarbonImmutable $selectedDay, Collection $timelineItems, CarbonImmutable $currentTime): Collection
    {
        $itemsByRow = collect();
        foreach ($timelineItems as $item) {
            $minutesFromStart = max(0, min(1439, $selectedDay->diffInMinutes($item['start_at'], false)));
            $rowIndex = intdiv((int)$minutesFromStart, ScheduleTime::GRID_MINUTES);
            $rowItems = $itemsByRow->get($rowIndex, collect());
            $rowItems->push($item);
            $itemsByRow->put($rowIndex, $rowItems);
        }

        $isToday = $selectedDay->toDateString() === $currentTime->toDateString();

        return collect(range(0, 47))->map(function (int $rowIndex) use ($selectedDay, $itemsByRow, $currentTime, $isToday): array {
            $start = $selectedDay->addMinutes($rowIndex * ScheduleTime::GRID_MINUTES);
            $end = $start->addMinutes(ScheduleTime::GRID_MINUTES);
            $isCurrent = $isToday && $currentTime->greaterThanOrEqualTo($start) && $currentTime->lessThan($end);
            $nowPosition = null;

            if ($isCurrent) {
                $minutesIntoRow = $start->diffInMinutes($currentTime);
                $nowPosition = min(100, max(0, ($minutesIntoRow / ScheduleTime::GRID_MINUTES) * 100));
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

    private function weekDays(DoctorProfile $doctor, CarbonImmutable $weekStart, CarbonImmutable $currentTime): Collection
    {
        $daysWithAppointments = Appointment::query()
            ->forDoctor($doctor)
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->where('start_at', '>=', $weekStart->startOfDay())
            ->where('start_at', '<', $weekStart->addWeek()->startOfDay())
            ->pluck('start_at')
            ->map(fn($startAt) => CarbonImmutable::parse($startAt)->toDateString())
            ->unique()
            ->all();

        $daysWithSlots = collect(range(0, 6))
            ->map(fn(int $i): CarbonImmutable => $weekStart->addDays($i))
            ->filter(fn(CarbonImmutable $date): bool => !$date->lessThan($currentTime->startOfDay()))
            ->filter(fn(CarbonImmutable $date): bool => $this->availability->agendaSlotsForDate($doctor, $date)->isNotEmpty())
            ->map(fn(CarbonImmutable $date): string => $date->toDateString())
            ->all();

        return collect(range(0, 6))->map(function (int $i) use ($weekStart, $daysWithAppointments, $daysWithSlots): array {
            $date = $weekStart->addDays($i);
            $dateStr = $date->toDateString();
            $hasAppointments = in_array($dateStr, $daysWithAppointments, true);
            $hasSlots = in_array($dateStr, $daysWithSlots, true);

            return [
                'date' => $date,
                'hasSlots' => $hasSlots,
                'hasAppointments' => $hasAppointments,
                'availabilityState' => $hasAppointments ? 'booked' : ($hasSlots ? 'open' : 'closed'),
            ];
        });
    }
}
