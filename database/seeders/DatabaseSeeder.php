<?php

namespace Database\Seeders;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
  public function run(): void
  {
    $patientUser = User::updateOrCreate(
      ['email' => 'patient@example.com'],
      [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'role' => User::ROLE_PATIENT,
        'password' => Hash::make('patient123'),
      ],
    );
    PatientProfile::updateOrCreate(
      ['user_id' => $patientUser->id],
      [
        'phone' => '3331234567',
        'address' => 'Via del Paziente 10',
      ],
    );

    $doctorUser = User::updateOrCreate(
      ['email' => 'doctor.derm@example.com'],
      [
        'first_name' => 'Kylian',
        'last_name' => 'Mbappe',
        'role' => User::ROLE_DOCTOR,
        'is_active' => true,
        'password' => Hash::make('doctor123'),
      ],
    );
    DoctorProfile::updateOrCreate(
      ['user_id' => $doctorUser->id],
      [
        'display_name' => 'Dott. Mbappe',
        'license_number' => 'DERM-001',
        'phone' => '3331000000',
        'clinic_address' => 'Via Roma 1',
      ],
    );

    $doctor = DoctorProfile::where('user_id', $doctorUser->id)->firstOrFail();

    foreach ([
      ['Visita dermatologica', MedicalService::CATEGORY_VISIT, 30, '120.00'],
      ['Controllo nei', MedicalService::CATEGORY_EXAM, 30, '90.00'],
      ['Dermatoscopia digitale', MedicalService::CATEGORY_EXAM, 30, '110.00'],
      ['Mappatura nei', MedicalService::CATEGORY_EXAM, 30, '140.00'],
      ['Controllo acne', MedicalService::CATEGORY_VISIT, 30, '95.00'],
      ['Trattamento cheratosi', MedicalService::CATEGORY_VISIT, 30, '130.00'],
      ['Consulenza dermatologica pediatrica', MedicalService::CATEGORY_VISIT, 30, '100.00'],
    ] as [$name, $category, $duration, $price]) {
      MedicalService::updateOrCreate(
        ['name' => $name],
        [
          'doctor_profile_id' => $doctor->id,
          'category' => $category,
          'duration_minutes' => $duration,
          'price' => $price,
          'is_active' => true,
        ],
      );
    }

    if (! $doctor->workingHours()->exists()) {
      foreach ([1, 2, 3, 4, 5] as $weekday) {
        WorkingHour::create([
          'doctor_profile_id' => $doctor->id,
          'weekday' => $weekday,
          'start_time' => '09:00:00',
          'end_time' => '12:00:00',
          'effective_from' => null,
          'effective_until' => null,
          'is_active' => true,
        ]);
      }
    }
  }
}
