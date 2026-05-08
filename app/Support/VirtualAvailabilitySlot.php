<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class VirtualAvailabilitySlot
{
  public function __construct(
    public readonly string $key,
    public readonly CarbonImmutable $start_at,
    public readonly CarbonImmutable $end_at,
    public readonly int $doctor_profile_id,
  ) {
  }
}
