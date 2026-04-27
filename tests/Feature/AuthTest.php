<?php

namespace Tests\Feature;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
  use RefreshDatabase;

  public function test_patient_can_register_and_reach_dashboard(): void
  {
    $response = $this->post('/register', [
      'username' => 'sara@example.com',
      'password' => 'strong-pass-123',
      'first_name' => 'Sara',
      'last_name' => 'Conti',
      'phone' => '+390000000',
    ]);

    $response->assertRedirect('/patient');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
      'username' => 'sara@example.com',
      'role' => User::ROLE_PATIENT,
    ]);
    $this->assertDatabaseHas('patient_profiles', [
      'phone' => '+390000000',
    ]);
  }

  public function test_register_rejects_short_password(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'short@example.com',
      'password' => 'short',
      'phone' => '+390000001',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('password');
    $this->assertGuest();
  }

  public function test_login_routes_roles_to_their_portals(): void
  {
    $patient = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $patient->id, 'phone' => '555-0100']);

    $response = $this->post('/login', [
      'username' => 'patient',
      'password' => 'patient123',
    ]);

    $response->assertRedirect('/patient');
    $this->assertAuthenticatedAs($patient);
  }

  public function test_login_rejects_invalid_credentials(): void
  {
    User::create([
      'username' => 'sara@example.com',
      'password' => Hash::make('strong-pass-123'),
      'role' => User::ROLE_PATIENT,
    ]);

    $response = $this->from('/login')->post('/login', [
      'username' => 'sara@example.com',
      'password' => 'wrong-pass-123',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('username');
    $this->assertGuest();
  }

  public function test_logout_clears_session(): void
  {
    $user = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
  }
}
