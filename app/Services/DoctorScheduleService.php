<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\ScheduleClosure;
use App\Models\SpecialOpening;
use Illuminate\Support\Collection;

class DoctorScheduleService
{
  public const CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD = 'confirm_appointment_cancellations';

  public function __construct(
    private readonly DoctorWorkingHoursService $workingHours,
    private readonly DoctorClosureService $closures,
    private readonly DoctorSpecialOpeningService $specialOpenings,
  ) {
  }

  public function weeklyTemplateForForm(DoctorProfile $doctor): array
  {
    return $this->workingHours->weeklyTemplateForForm($doctor);
  }

  public function replaceWeeklyTemplate(DoctorProfile $doctor, array $input, bool $confirmed = false): void
  {
    $this->workingHours->replaceWeeklyTemplate($doctor, $input, $confirmed);
  }

  public function createClosure(DoctorProfile $doctor, array $input, bool $confirmed = false): Collection
  {
    return $this->closures->createClosure($doctor, $input, $confirmed);
  }

  public function updateClosure(DoctorProfile $doctor, ScheduleClosure $closure, array $input, bool $confirmed = false): void
  {
    $this->closures->updateClosure($doctor, $closure, $input, $confirmed);
  }

  public function deleteClosure(DoctorProfile $doctor, ScheduleClosure $closure, bool $confirmed = false): void
  {
    $this->closures->deleteClosure($doctor, $closure, $confirmed);
  }

  public function createSpecialOpening(DoctorProfile $doctor, array $input): SpecialOpening
  {
    return $this->specialOpenings->createSpecialOpening($doctor, $input);
  }

  public function updateSpecialOpening(
    DoctorProfile $doctor,
    SpecialOpening $specialOpening,
    array $input,
    bool $confirmed = false,
  ): void {
    $this->specialOpenings->updateSpecialOpening($doctor, $specialOpening, $input, $confirmed);
  }

  public function deleteSpecialOpening(DoctorProfile $doctor, SpecialOpening $specialOpening, bool $confirmed = false): void
  {
    $this->specialOpenings->deleteSpecialOpening($doctor, $specialOpening, $confirmed);
  }
}
