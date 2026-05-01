<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
  use RefreshDatabase;

  public function test_guest_sees_landing_page_at_root(): void
  {
    $this->get('/')
      ->assertOk()
      ->assertSee('La tua pelle')
      ->assertDontSee('data-landing-services-prev', false)
      ->assertDontSee('data-landing-services-next', false);
  }

  public function test_landing_page_contains_login_and_register_links(): void
  {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="/login"', false);
    $response->assertSee('href="/register"', false);
  }

  public function test_authenticated_patient_is_redirected_to_portal(): void
  {
    $user = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);

    $this->actingAs($user)->get('/')->assertRedirect('/patient');
  }

  public function test_authenticated_doctor_is_redirected_to_portal(): void
  {
    $user = User::create([
      'username' => 'doctor',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);

    $this->actingAs($user)->get('/')->assertRedirect('/doctor/schedule');
  }

  public function test_landing_page_shows_chi_ti_segue_section(): void
  {
    $this->get('/')->assertOk()->assertSee('Chi ti segue');
  }

  public function test_landing_page_shows_doctor_display_name_from_database(): void
  {
    $user = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    DoctorProfile::create([
      'user_id' => $user->id,
      'display_name' => 'Dott. Giulia Ferretti',
    ]);

    $this->get('/')
      ->assertOk()
      ->assertSee('Dott. Giulia Ferretti');
  }

  public function test_landing_page_uses_general_doctor_image(): void
  {
    $this->get('/')
      ->assertOk()
      ->assertSee('src="/images/general.png"', false)
      ->assertSee('alt="Foto Dott. Mbappe"', false);
  }

  public function test_landing_page_shows_active_medical_services_as_feature_cards(): void
  {
    MedicalService::create([
      'name' => 'Dermatoscopia digitale',
      'category' => MedicalService::CATEGORY_EXAM,
      'duration_minutes' => 30,
      'price' => '90.00',
      'is_active' => true,
    ]);
    MedicalService::create([
      'name' => 'Trattamento non visibile',
      'category' => MedicalService::CATEGORY_VISIT,
      'duration_minutes' => 30,
      'is_active' => false,
    ]);

    $this->get('/')
      ->assertOk()
      ->assertSee('Dermatoscopia digitale')
      ->assertSee('Esame dermatologico')
      ->assertDontSee('Trattamento non visibile');
  }

  public function test_landing_page_allows_scrolling_when_many_services_are_available(): void
  {
    foreach (range(1, 4) as $index) {
      MedicalService::create([
        'name' => "Trattamento {$index}",
        'category' => MedicalService::CATEGORY_VISIT,
        'duration_minutes' => 30,
        'is_active' => true,
      ]);
    }

    $this->get('/')
      ->assertOk()
      ->assertSee('Trattamento 4')
      ->assertSee('data-landing-services-prev', false)
      ->assertSee('data-landing-services-next', false);
  }
}
