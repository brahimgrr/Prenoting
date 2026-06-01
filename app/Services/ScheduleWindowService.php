<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use App\Models\WorkingHour;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleWindowService
{
    public function openingWindowsForDate(
        DoctorProfile          $doctor,
        CarbonImmutable|string $date,
        ?SpecialOpening        $excludingSpecialOpening = null,
    ): Collection
    {
        $day = ScheduleTime::normalizeDate($date);
        $workingHours = $doctor->workingHours()
            ->where('is_active', true)
            ->where('weekday', $day->dayOfWeekIso)
            ->where(function ($query) use ($day): void {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day->toDateString());
            })
            ->where(function ($query) use ($day): void {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $day->toDateString());
            })
            ->get()
            ->map(fn(WorkingHour $window): array => $this->window($day, $window->start_time, $window->end_time));

        return $this->sortedWindows(
            $workingHours->concat($this->specialOpeningWindowsForDate($doctor, $day, $excludingSpecialOpening))
        );
    }

    private function window(CarbonImmutable $day, string $startTime, string $endTime): array
    {
        return [
            'start_at' => ScheduleTime::combine($day, $startTime),
            'end_at' => ScheduleTime::combine($day, $endTime),
        ];
    }

    private function sortedWindows(Collection $windows): Collection
    {
        return $windows
            ->filter(fn(array $window): bool => $window['end_at']->greaterThan($window['start_at']))
            ->sortBy(fn(array $window): int => $window['start_at']->getTimestamp())
            ->values();
    }

    private function specialOpeningWindowsForDate(
        DoctorProfile          $doctor,
        CarbonImmutable|string $date,
        ?SpecialOpening        $excludingSpecialOpening = null,
    ): Collection
    {
        $day = ScheduleTime::normalizeDate($date);

        return $doctor->specialOpenings()
            ->whereDate('date', $day->toDateString())
            ->when($excludingSpecialOpening, fn($query) => $query->whereKeyNot($excludingSpecialOpening->id))
            ->get()
            ->map(fn(SpecialOpening $window): array => $this->window($day, $window->start_time, $window->end_time));
    }

    public function openingWindowsFromTemplate(
        DoctorProfile          $doctor,
        CarbonImmutable|string $date,
        array                  $weeklyWindows,
        ?SpecialOpening        $excludingSpecialOpening = null,
    ): Collection
    {
        $day = ScheduleTime::normalizeDate($date);
        $templateWindows = collect($weeklyWindows[$day->dayOfWeekIso] ?? [])
            ->map(fn(array $window): array => $this->window($day, $window['start_time'], $window['end_time']));

        return $this->sortedWindows(
            $templateWindows->concat($this->specialOpeningWindowsForDate($doctor, $day, $excludingSpecialOpening))
        );
    }

    public function closureIntervalsForDate(DoctorProfile $doctor, CarbonImmutable|string $date): Collection
    {
        return $this->closuresForDate($doctor, $date)
            ->map(fn(array $closure): array => [
                'start_at' => $closure['start_at'],
                'end_at' => $closure['end_at'],
            ]);
    }

    public function closuresForDate(DoctorProfile $doctor, CarbonImmutable|string $date): Collection
    {
        $day = ScheduleTime::normalizeDate($date);

        return ScheduleClosure::query()
            ->where('doctor_profile_id', $doctor->id)
            ->whereDate('date', $day->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(function (ScheduleClosure $closure) use ($day): array {
                return [
                    'closure' => $closure,
                    'start_at' => $closure->start_time
                        ? ScheduleTime::combine($day, $closure->start_time)
                        : $day->startOfDay(),
                    'end_at' => $closure->end_time
                        ? ScheduleTime::combine($day, $closure->end_time)
                        : $day->endOfDay(),
                ];
            });
    }

    public function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, Collection $intervals): bool
    {
        return $intervals->contains(
            fn(array $interval): bool => $start->lessThan($interval['end_at']) && $end->greaterThan($interval['start_at'])
        );
    }
}
