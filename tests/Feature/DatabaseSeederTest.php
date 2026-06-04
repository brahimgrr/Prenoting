<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
  use RefreshDatabase;

  public function test_database_seeder_creates_default_working_hours_for_new_seeded_doctor(): void
  {
    $this->seed(DatabaseSeeder::class);

    $doctor = DoctorProfile::whereHas('user', fn ($query) => $query->where('email', 'doctor.derm@example.com'))->firstOrFail();

    $this->assertSame(5, $doctor->workingHours()->count());
    foreach ([1, 2, 3, 4, 5] as $weekday) {
      $this->assertDatabaseHas('working_hours', [
        'doctor_profile_id' => $doctor->id,
        'weekday' => $weekday,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
      ]);
    }
  }

  public function test_database_seeder_does_not_append_default_working_hours_to_existing_template(): void
  {
    $doctorUser = User::create([
      'email' => 'doctor.derm@example.com',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
    ]);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '08:00',
      'end_time' => '12:00',
      'is_active' => true,
    ]);
    WorkingHour::create([
      'doctor_profile_id' => $doctor->id,
      'weekday' => 5,
      'start_time' => '09:00',
      'end_time' => '10:00',
      'is_active' => true,
    ]);

    $this->seed(DatabaseSeeder::class);

    $this->assertSame(2, $doctor->workingHours()->count());
    $this->assertDatabaseMissing('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 1,
      'start_time' => '09:00:00',
      'end_time' => '12:00:00',
    ]);
    $this->assertDatabaseMissing('working_hours', [
      'doctor_profile_id' => $doctor->id,
      'weekday' => 5,
      'start_time' => '09:00:00',
      'end_time' => '12:00:00',
    ]);
  }
}
