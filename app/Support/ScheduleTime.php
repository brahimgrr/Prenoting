<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScheduleTime
{
    public const GRID_MINUTES = 30;

    public static function parseSlotStart(string $slotStart): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($slotStart)->second(0)->microsecond(0);
        } catch (Throwable) {
            return null;
        }
    }

    public static function normalizeDate(CarbonImmutable|string $date): CarbonImmutable
    {
        return $date instanceof CarbonImmutable
            ? $date->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();
    }

    public static function combine(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        if ($hour === 24 && $minute === 0) {
            return $date->addDay()->startOfDay();
        }

        return $date->setTime($hour, $minute);
    }

    public static function assertRange(string $start, string $end, string $field, string $message): void
    {
        self::assertGridTime($start, $field);
        self::assertGridTime($end, $field, allowEndOfDay: true);

        if (self::toMinutes($end) <= self::toMinutes($start)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public static function assertGridTime(string $time, string $field, bool $allowEndOfDay = false): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw ValidationException::withMessages([$field => 'Usa un orario valido nel formato HH:MM.']);
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));
        $isEndOfDay = $hour === 24 && $minute === 0;

        if (
            !($allowEndOfDay && $isEndOfDay)
            && ($hour < 0 || $hour > 23 || !in_array($minute, [0, self::GRID_MINUTES], true))
        ) {
            throw ValidationException::withMessages([$field => 'Gli orari devono rispettare la griglia di 30 minuti.']);
        }
    }

    public static function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
