<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\SpecialOpening;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorSpecialOpeningService
{
  public function __construct(
    private readonly ScheduleWindowService $windows,
    private readonly ScheduleAppointmentImpactService $appointments,
  ) {
  }

  public function createSpecialOpening(DoctorProfile $doctor, array $input): SpecialOpening
  {
    $opening = $this->normalizeSpecialOpeningInput($input);
    $this->rejectPastDate($opening['date']);
    $this->rejectRedundantSpecialOpening($doctor, $opening);

    return SpecialOpening::create([
      'doctor_profile_id' => $doctor->id,
      'date' => $opening['date'],
      'start_time' => $opening['start_time'],
      'end_time' => $opening['end_time'],
      'note' => $opening['note'],
    ]);
  }

  public function updateSpecialOpening(
    DoctorProfile $doctor,
    SpecialOpening $specialOpening,
    array $input,
    bool $confirmed = false,
  ): void {
    abort_unless($doctor->id === $specialOpening->doctor_profile_id, 404);

    $opening = $this->normalizeSpecialOpeningInput($input);
    $this->rejectRedundantSpecialOpening($doctor, $opening, $specialOpening);

    DB::transaction(function () use ($doctor, $specialOpening, $opening, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->appointmentsUnsupportedBy($doctor, null, $specialOpening, $opening),
        ScheduleAppointmentImpactService::WORKING_HOURS_CANCELLATION_REASON,
        $confirmed,
      );

      $specialOpening->forceFill($opening)->save();
    });
  }

  public function deleteSpecialOpening(DoctorProfile $doctor, SpecialOpening $specialOpening, bool $confirmed = false): void
  {
    abort_unless($doctor->id === $specialOpening->doctor_profile_id, 404);

    DB::transaction(function () use ($doctor, $specialOpening, $confirmed): void {
      $this->appointments->cancelOrRequestConfirmation(
        $this->appointments->appointmentsUnsupportedBy($doctor, null, $specialOpening),
        ScheduleAppointmentImpactService::WORKING_HOURS_CANCELLATION_REASON,
        $confirmed,
      );

      $specialOpening->delete();
    });
  }

  private function normalizeSpecialOpeningInput(array $input): array
  {
    $date = CarbonImmutable::parse((string) $input['date'])->startOfDay();
    $startTime = trim((string) ($input['start_time'] ?? ''));
    $endTime = trim((string) ($input['end_time'] ?? ''));

    ScheduleTime::assertRange($startTime, $endTime, 'special_opening', 'La fine deve essere successiva all\'inizio.');

    return [
      'date' => $date->toDateString(),
      'start_time' => $startTime,
      'end_time' => $endTime,
      'note' => trim((string) ($input['note'] ?? '')) ?: null,
    ];
  }

  private function rejectPastDate(string $date): void
  {
    if (CarbonImmutable::parse($date)->startOfDay()->lessThan(CarbonImmutable::now()->startOfDay())) {
      throw ValidationException::withMessages(['special_opening' => 'Non puoi creare aperture extra in date passate.']);
    }
  }

  private function rejectRedundantSpecialOpening(
    DoctorProfile $doctor,
    array $opening,
    ?SpecialOpening $excluding = null,
  ): void {
    $date = CarbonImmutable::parse($opening['date'])->startOfDay();
    $start = ScheduleTime::combine($date, $opening['start_time']);
    $end = ScheduleTime::combine($date, $opening['end_time']);

    foreach ($this->windows->openingWindowsForDate($doctor, $date, $excluding) as $window) {
      if ($window['start_at']->lessThanOrEqualTo($start) && $window['end_at']->greaterThanOrEqualTo($end)) {
        throw ValidationException::withMessages([
          'special_opening' => 'Questa apertura e gia coperta dagli orari esistenti.',
        ]);
      }
    }
  }
}
