<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkingHoursCleanupMigrationTest extends TestCase
{
  use RefreshDatabase;

  public function test_cleanup_migration_deletes_all_existing_working_hours(): void
  {
    $doctorUser = User::create([
      'email' => 'doctor.derm@example.com',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
      'is_active' => true,
    ]);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '09:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '14:00',
      'end_time' => '18:00',
      'is_active' => true,
    ]);

    $this->assertSame(2, WorkingHour::count());

    $migration = include database_path('migrations/2026_05_30_000001_clear_existing_working_hours.php');
    $migration->up();

    $this->assertSame(0, WorkingHour::count());
  }
}
