<?php

namespace Database\Seeders;

use App\Models\AvailabilitySlot;
use App\Models\ClinicLocation;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\Specialty;
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

    $staff = User::updateOrCreate(
      ['username' => 'staff'],
      [
        'email' => 'staff@example.com',
        'first_name' => 'Staff',
        'last_name' => 'Accettazione',
        'role' => User::ROLE_STAFF,
        'password' => Hash::make('staff123'),
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
        'identity_code' => 'PAT-001',
      ],
    );

    $specialties = [];
    foreach ([
      ['Cardiologia', "Cura del cuore e dell'apparato vascolare"],
      ['Dermatologia', 'Salute e trattamento della pelle'],
      ['Radiologia', 'Diagnostica per immagini'],
    ] as [$name, $description]) {
      $specialties[$name] = Specialty::updateOrCreate(
        ['name' => $name],
        ['description' => $description],
      );
    }

    $doctorSpecs = [
      ['doctor.heart', 'Amelia', 'Cuori', 'Dott.ssa Amelia Cuori', 'Cardiologia', 'CARD-001'],
      ['doctor.skin', 'Dorian', 'Pelle', 'Dott. Dorian Pelle', 'Dermatologia', 'DERM-001'],
    ];
    $doctors = [];
    foreach ($doctorSpecs as [$username, $firstName, $lastName, $displayName, $specialtyName, $license]) {
      $doctorUser = User::updateOrCreate(
        ['username' => $username],
        [
          'email' => "{$username}@example.com",
          'first_name' => $firstName,
          'last_name' => $lastName,
          'role' => User::ROLE_DOCTOR,
          'password' => Hash::make('doctor123'),
        ],
      );
      $doctors[$displayName] = DoctorProfile::updateOrCreate(
        ['user_id' => $doctorUser->id],
        [
          'display_name' => $displayName,
          'specialty_id' => $specialties[$specialtyName]->id,
          'license_number' => $license,
          'is_active' => true,
        ],
      );
    }

    $clinics = [];
    foreach ([
      ['Ambulatorio Centro', 'Via Roma 1', '555-1000'],
      ['Centro Medico Nord', 'Viale Nord 200', '555-2000'],
    ] as [$name, $address, $phone]) {
      $clinics[$name] = ClinicLocation::updateOrCreate(
        ['name' => $name],
        ['address' => $address, 'phone' => $phone, 'is_active' => true],
      );
    }

    $services = [];
    foreach ([
      ['Visita cardiologica', MedicalService::CATEGORY_VISIT, 'Cardiologia', 30, '150.00'],
      ['Elettrocardiogramma', MedicalService::CATEGORY_EXAM, 'Cardiologia', 20, '80.00'],
      ['Visita dermatologica', MedicalService::CATEGORY_VISIT, 'Dermatologia', 30, '120.00'],
      ['Ecografia', MedicalService::CATEGORY_EXAM, 'Radiologia', 45, '200.00'],
    ] as [$name, $category, $specialtyName, $duration, $price]) {
      $services[$name] = MedicalService::updateOrCreate(
        ['name' => $name],
        [
          'category' => $category,
          'specialty_id' => $specialties[$specialtyName]->id,
          'duration_minutes' => $duration,
          'price' => $price,
          'is_active' => true,
        ],
      );
    }

    $doctorServices = [
      'Dott.ssa Amelia Cuori' => ['Visita cardiologica', 'Elettrocardiogramma', 'Ecografia'],
      'Dott. Dorian Pelle' => ['Visita dermatologica'],
    ];
    foreach ($doctorServices as $doctorName => $serviceNames) {
      foreach ($serviceNames as $serviceName) {
        DoctorService::firstOrCreate([
          'doctor_id' => $doctors[$doctorName]->id,
          'service_id' => $services[$serviceName]->id,
        ]);
      }
    }

    $base = CarbonImmutable::now()->setTime(9, 0, 0);
    $clinicValues = array_values($clinics);
    $doctorValues = array_values($doctors);
    foreach (range(1, 7) as $dayOffset) {
      foreach ($doctorValues as $doctorIndex => $doctor) {
        foreach (range(0, 2) as $hourOffset) {
          $startAt = $base->addDays($dayOffset)->addHours(($doctorIndex * 3) + $hourOffset);
          AvailabilitySlot::updateOrCreate(
            [
              'doctor_id' => $doctor->id,
              'start_at' => $startAt,
            ],
            [
              'clinic_id' => $clinicValues[($doctorIndex + $hourOffset) % count($clinicValues)]->id,
              'end_at' => $startAt->addMinutes(30),
              'is_blocked' => false,
              'is_booked' => false,
            ],
          );
        }
      }
    }
  }
}
