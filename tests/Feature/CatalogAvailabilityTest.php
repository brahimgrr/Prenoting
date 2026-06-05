<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_endpoint_returns_active_services_and_filters_search(): void
    {
        $doctor = $this->doctor();
        $matched = MedicalService::create(['doctor_profile_id' => $doctor->id, 'name' => 'Visita dermatologica']);
        MedicalService::create(['doctor_profile_id' => $doctor->id, 'name' => 'Mappatura nei']);
        MedicalService::create(['doctor_profile_id' => $doctor->id, 'name' => 'Servizio inattivo', 'is_active' => false]);

        $response = $this->getJson('/catalog/services?search=derm');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $matched->id);
        $response->assertJsonMissingPath('0.specialty_name');
    }

    public function test_availability_filters_available_future_slots_by_service(): void
    {
        $doctorUser = User::create(['email' => 'doctor@example.com', 'password' => 'x']);
        $this->assignRole($doctorUser, User::ROLE_DOCTOR);
        $doctor = DoctorProfile::create(['user_id' => $doctorUser->id, 'display_name' => 'Dott. Test']);
        $service = MedicalService::create(['doctor_profile_id' => $doctor->id, 'name' => 'Visita dermatologica']);
        $start = CarbonImmutable::now()->addDay()->setTime(9, 0);
        $doctor->specialOpenings()->create([
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->addHour()->format('H:i:s'),
        ]);
        $doctor->closures()->create([
            'date' => $start->toDateString(),
            'start_time' => $start->addMinutes(30)->format('H:i:s'),
            'end_time' => $start->addHour()->format('H:i:s'),
            'reason' => 'Blocco',
        ]);

        $response = $this->getJson('/availability?service='.$service->id);

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.slot_key', $start->format('Y-m-d\TH:i'));
        $response->assertJsonMissingPath('0.doctor_name');
        $response->assertJsonMissingPath('0.clinic_name');
    }

    public function test_availability_uses_user_active_flag_for_doctors(): void
    {
        $doctorUser = User::create([
            'email' => 'doctor@example.com',
            'password' => 'x',
            'is_active' => false,
        ]);
        $this->assignRole($doctorUser, User::ROLE_DOCTOR);
        $doctor = DoctorProfile::create(['user_id' => $doctorUser->id, 'display_name' => 'Dott. Test']);
        $service = MedicalService::create(['doctor_profile_id' => $doctor->id, 'name' => 'Visita dermatologica']);
        $start = CarbonImmutable::now()->addDay()->setTime(9, 0);
        $doctor->specialOpenings()->create([
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->addHour()->format('H:i:s'),
        ]);

        $this->getJson('/availability?service='.$service->id)->assertNotFound();
    }

    public function test_availability_rejects_malformed_filters(): void
    {
        $this->getJson('/availability?date=bad')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->getJson('/availability?service=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service');
    }

    private function doctor(): DoctorProfile
    {
        $user = User::create(['email' => 'doctor@example.com', 'password' => 'x']);
        $this->assignRole($user, User::ROLE_DOCTOR);

        return DoctorProfile::create(['user_id' => $user->id, 'display_name' => 'Dott. Test']);
    }
}
