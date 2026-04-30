<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
  use RefreshDatabase;

  public function test_guest_sees_landing_page_at_root(): void
  {
    $this->get('/')->assertOk()->assertSee('La tua pelle');
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

    $this->actingAs($user)->get('/')->assertRedirect('/doctor');
  }
}
