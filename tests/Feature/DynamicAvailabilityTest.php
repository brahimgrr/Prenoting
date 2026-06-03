<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynamicAvailabilityTest extends TestCase
{
  use RefreshDatabase;

  public function test_generates_virtual_slots_from_doctor_working_hours(): void
  {
    [$patientUser, $patient, $doctor, $service] = $this->bookingContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $this->workingHour($doctor, $date->dayOfWeekIso, '09:00', '10:30');

    $slots = app(AvailabilityService::class)->availableSlotsForDate($doctor, $service, $date);

    $this->assertSame(['09:00', '09:30', '10:00'], $slots->map(fn ($slot) => $slot->start_at->format('H:i'))->all());
    $this->assertSame($date->setTime(9, 0)->format('Y-m-d\TH:i'), $slots->first()->key);
  }

  public function test_closures_override_working_hours_and_special_openings(): void
  {
    [$patientUser, $patient, $doctor, $service] = $this->bookingContext();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $this->workingHour($doctor, $date->dayOfWeekIso, '09:00', '11:00');
    $doctor->specialOpenings()->create([
      'date' => $date->toDateString(),
      'start_time' => '11:00',
      'end_time' => '12:00',
      'note' => 'Apertura extra',
    ]);
    $doctor->closures()->create([
      'date' => $date->toDateString(),
      'start_time' => '09:30',
      'end_time' => '11:30',
      'reason' => 'Riunione',
    ]);

    $slots = app(AvailabilityService::class)->availableSlotsForDate($doctor, $service, $date);

    $this->assertSame(['09:00', '11:30'], $slots->map(fn ($slot) => $slot->start_at->format('H:i'))->all());
  }

  public function test_service_duration_must_fit_inside_open_window(): void
  {
    [$patientUser, $patient, $doctor, $service] = $this->bookingContext();
    $service->forceFill(['duration_minutes' => 60])->save();
    $date = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    $this->workingHour($doctor, $date->dayOfWeekIso, '09:00', '10:30');

    $slots = app(AvailabilityService::class)->availableSlotsForDate($doctor, $service, $date);

    $this->assertSame(['09:00', '09:30'], $slots->map(fn ($slot) => $slot->start_at->format('H:i'))->all());
  }

  private function bookingContext(): array
  {
    [$patientUser, $patient] = $this->patient('patient@example.com');
    $doctorUser = User::create([
      'email' => 'doctor.derm@example.com',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
    ]);
    $service = MedicalService::create([
      'doctor_profile_id' => $doctor->id,
      'name' => 'Visita dermatologica',
      'duration_minutes' => 30,
    ]);

    return [$patientUser, $patient, $doctor, $service];
  }

  private function patient(string $email): array
  {
    $user = User::create([
      'email' => $email,
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $profile = PatientProfile::create(['user_id' => $user->id, 'phone' => '555-0100']);

    return [$user, $profile];
  }

  private function workingHour(DoctorProfile $doctor, int $weekday, string $start, string $end): WorkingHour
  {
    return WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => $weekday,
      'start_time' => $start,
      'end_time' => $end,
      'is_active' => true,
    ]);
  }
}
