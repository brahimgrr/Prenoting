<?php

namespace Tests\Feature;

use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleTimeTest extends TestCase
{
  public function test_combines_midnight_end_time_as_next_day_start(): void
  {
    $date = CarbonImmutable::parse('2026-05-18')->startOfDay();

    $combined = ScheduleTime::combine($date, '24:00');

    $this->assertSame('2026-05-19 00:00', $combined->format('Y-m-d H:i'));
  }

  public function test_rejects_non_half_hour_grid_times(): void
  {
    $this->expectException(ValidationException::class);

    ScheduleTime::assertGridTime('09:15', 'working_hours');
  }
}
