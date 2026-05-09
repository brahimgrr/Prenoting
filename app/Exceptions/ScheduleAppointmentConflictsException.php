<?php

namespace App\Exceptions;

use Illuminate\Support\Collection;
use RuntimeException;

class ScheduleAppointmentConflictsException extends RuntimeException
{
  public function __construct(
    private readonly Collection $appointments,
    private readonly string $reason,
  )
  {
    parent::__construct('Schedule operation requires appointment cancellation confirmation.');
  }

  public function appointments(): Collection
  {
    return $this->appointments;
  }

  public function reason(): string
  {
    return $this->reason;
  }
}
