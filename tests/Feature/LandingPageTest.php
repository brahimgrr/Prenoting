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
      ->assertSee('Dott. Mbappe')
      ->assertSee('Specialista in Dermatologia')
      ->assertSee('Indirizzo')
      ->assertSee('Telefono')
      ->assertSee('Email')
      ->assertDontSee('Prestazioni disponibili')
      ->assertDontSee('Chi ti segue')
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

    $this->actingAs($user)->get('/')->assertRedirect('/doctor/agenda');
  }

  public function test_landing_page_shows_clean_doctor_intro(): void
  {
    $this->get('/')
      ->assertOk()
      ->assertSee('Studio Dermatologico')
      ->assertSee('Cura della pelle');
  }

  public function test_landing_page_shows_doctor_display_name_from_database(): void
  {
    $user = User::create([
      'username' => 'doctor.derm',
      'email' => 'giulia.ferretti@example.com',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    DoctorProfile::create([
      'user_id' => $user->id,
      'display_name' => 'Dott. Giulia Ferretti',
      'phone' => '555-2020',
      'clinic_address' => 'Via Milano 20',
    ]);

    $this->get('/')
      ->assertOk()
      ->assertSee('Dott. Giulia Ferretti')
      ->assertSee('Via Milano 20')
      ->assertSee('555-2020')
      ->assertSee('giulia.ferretti@example.com')
      ->assertDontSee('02 1234567')
      ->assertDontSee('info@studiodermatologo.it');
  }

  public function test_landing_page_uses_general_doctor_image(): void
  {
    $this->get('/')
      ->assertOk()
      ->assertSee('src="/images/pigeon.png"', false)
      ->assertSee('alt="Foto Dott. Mbappe"', false);
  }

  public function test_landing_page_keeps_medical_services_off_the_clean_intro(): void
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
      ->assertDontSee('Dermatoscopia digitale')
      ->assertDontSee('Esame dermatologico')
      ->assertDontSee('Trattamento non visibile');
  }

  public function test_landing_page_does_not_render_service_carousel_controls(): void
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
      ->assertDontSee('Trattamento 4')
      ->assertDontSee('data-landing-services-prev', false)
      ->assertDontSee('data-landing-services-next', false);
  }
}
