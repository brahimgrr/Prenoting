<?php

namespace Database\Seeders;

use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
  public function run(): void
  {
    $admin = User::updateOrCreate(
      ['username' => 'admin'],
      [
        'email' => 'admin@example.com',
        'first_name' => 'Amministratore',
        'last_name' => 'Sistema',
        'role' => User::ROLE_ADMIN,
        'password' => Hash::make('admin123'),
      ],
    );

    $patientUser = User::updateOrCreate(
      ['username' => 'patient'],
      [
        'email' => 'patient@example.com',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'role' => User::ROLE_PATIENT,
        'password' => Hash::make('patient123'),
      ],
    );
    PatientProfile::updateOrCreate(
      ['user_id' => $patientUser->id],
      [
        'phone' => '555-0100',
        'address' => 'Via del Paziente 10',
      ],
    );

    $doctorUser = User::updateOrCreate(
      ['username' => 'doctor.derm'],
      [
        'email' => 'doctor.derm@example.com',
        'first_name' => 'Dorian',
        'last_name' => 'Pelle',
        'role' => User::ROLE_DOCTOR,
        'password' => Hash::make('doctor123'),
      ],
    );
    DoctorProfile::updateOrCreate(
      ['user_id' => $doctorUser->id],
      [
        'display_name' => 'Dott. Dorian Pelle',
        'license_number' => 'DERM-001',
        'is_active' => true,
      ],
    );

    foreach ([
      ['Visita dermatologica', MedicalService::CATEGORY_VISIT, 30, '120.00'],
      ['Controllo nei', MedicalService::CATEGORY_EXAM, 30, '90.00'],
    ] as [$name, $category, $duration, $price]) {
      MedicalService::updateOrCreate(
        ['name' => $name],
        [
          'category' => $category,
          'duration_minutes' => $duration,
          'price' => $price,
          'is_active' => true,
        ],
      );
    }

    $base = CarbonImmutable::now()->setTime(9, 0, 0);
    foreach (range(1, 7) as $dayOffset) {
      foreach (range(0, 2) as $hourOffset) {
        $startAt = $base->addDays($dayOffset)->addHours($hourOffset);
        AvailabilitySlot::updateOrCreate(
          ['start_at' => $startAt],
          [
            'end_at' => $startAt->addMinutes(30),
            'is_blocked' => false,
            'is_booked' => false,
          ],
        );
      }
    }
  }
}
