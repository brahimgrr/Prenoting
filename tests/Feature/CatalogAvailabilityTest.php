<?php

namespace Tests\Feature;

use App\Models\AvailabilitySlot;
use App\Models\ClinicLocation;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\MedicalService;
use App\Models\Specialty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogAvailabilityTest extends TestCase
{
  use RefreshDatabase;

  public function test_services_endpoint_returns_active_services_and_filters_search(): void
  {
    $specialty = Specialty::create(['name' => 'Cardiologia']);
    $matched = MedicalService::create(['name' => 'Visita cardiologica', 'specialty_id' => $specialty->id]);
    MedicalService::create(['name' => 'Visita dermatologica', 'specialty_id' => $specialty->id]);
    MedicalService::create(['name' => 'Servizio inattivo', 'specialty_id' => $specialty->id, 'is_active' => false]);

    $response = $this->getJson('/catalog/services?search=cardio');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $matched->id);
    $response->assertJsonPath('0.specialty_name', 'Cardiologia');
  }

  public function test_doctors_endpoint_returns_active_doctors_with_service_ids(): void
  {
    $specialty = Specialty::create(['name' => 'Cardiologia']);
    $doctor = $this->doctor('doctor.heart', 'Dott.ssa Amelia Cuori', $specialty);
    $inactive = $this->doctor('doctor.away', 'Dott. Assente', $specialty, false);
    $service = MedicalService::create(['name' => 'Visita cardiologica', 'specialty_id' => $specialty->id]);
    DoctorService::create(['doctor_id' => $doctor->id, 'service_id' => $service->id]);

    $response = $this->getJson('/catalog/doctors');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $doctor->id);
    $response->assertJsonPath('0.service_ids.0', $service->id);
    $this->assertNotEquals($inactive->id, $response->json('0.id'));
  }

  public function test_availability_filters_available_future_slots_by_service(): void
  {
    $specialty = Specialty::create(['name' => 'Cardiologia']);
    $service = MedicalService::create(['name' => 'Visita cardiologica', 'specialty_id' => $specialty->id]);
    $doctor = $this->doctor('doctor.heart', 'Dott.ssa Amelia Cuori', $specialty);
    DoctorService::create(['doctor_id' => $doctor->id, 'service_id' => $service->id]);
    $clinic = ClinicLocation::create(['name' => 'Ambulatorio Centro', 'address' => 'Via Roma 1']);
    $start = CarbonImmutable::now()->addDay()->setTime(9, 0);
    $visible = AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
    ]);
    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $start->addHour(),
      'end_at' => $start->addMinutes(90),
      'is_blocked' => true,
    ]);

    $response = $this->getJson('/availability?service='.$service->id);

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $visible->id);
    $response->assertJsonPath('0.doctor_name', 'Dott.ssa Amelia Cuori');
    $response->assertJsonPath('0.clinic_name', 'Ambulatorio Centro');
  }

  public function test_availability_rejects_malformed_filters(): void
  {
    $this->getJson('/availability?doctor=abc')
      ->assertUnprocessable()
      ->assertJsonValidationErrors('doctor');

    $this->getJson('/availability?date=bad')
      ->assertUnprocessable()
      ->assertJsonValidationErrors('date');
  }

  private function doctor(string $username, string $displayName, Specialty $specialty, bool $active = true): DoctorProfile
  {
    $user = User::create([
      'username' => $username,
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);

    return DoctorProfile::create([
      'user_id' => $user->id,
      'display_name' => $displayName,
      'specialty_id' => $specialty->id,
      'is_active' => $active,
    ]);
  }
}
