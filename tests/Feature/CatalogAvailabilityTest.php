<?php

namespace Tests\Feature;

use App\Models\AvailabilitySlot;
use App\Models\MedicalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAvailabilityTest extends TestCase
{
  use RefreshDatabase;

  public function test_services_endpoint_returns_active_services_and_filters_search(): void
  {
    $matched = MedicalService::create(['name' => 'Visita dermatologica']);
    MedicalService::create(['name' => 'Mappatura nei']);
    MedicalService::create(['name' => 'Servizio inattivo', 'is_active' => false]);

    $response = $this->getJson('/catalog/services?search=derm');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $matched->id);
    $response->assertJsonMissingPath('0.specialty_name');
  }

  public function test_availability_filters_available_future_slots_by_service(): void
  {
    $service = MedicalService::create(['name' => 'Visita dermatologica']);
    $start = CarbonImmutable::now()->addDay()->setTime(9, 0);
    $visible = AvailabilitySlot::create([
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
    ]);
    AvailabilitySlot::create([
      'start_at' => $start->addHour(),
      'end_at' => $start->addMinutes(90),
      'is_blocked' => true,
    ]);

    $response = $this->getJson('/availability?service='.$service->id);

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $visible->id);
    $response->assertJsonMissingPath('0.doctor_name');
    $response->assertJsonMissingPath('0.clinic_name');
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

  public function test_database_config_does_not_expose_redis_section(): void
  {
    $config = require config_path('database.php');

    $this->assertArrayHasKey('connections', $config);
    $this->assertArrayNotHasKey('redis', $config);
  }
}
