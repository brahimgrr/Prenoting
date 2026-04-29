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
      'password_confirmation' => 'strong-pass-123',
      'first_name' => 'Sara',
      'last_name' => 'Conti',
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'F',
      'phone' => '+390000000',
    ]);

    $response->assertRedirect('/patient');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
      'username' => 'sara@example.com',
      'role' => User::ROLE_PATIENT,
    ]);
    $this->assertDatabaseHas('patient_profiles', [
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'F',
      'phone' => '+390000000',
      'codice_fiscale' => 'CNTSRA90E61H501K',
    ]);
  }

  public function test_register_rejects_short_password(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'short@example.com',
      'password' => 'short',
      'password_confirmation' => 'short',
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'M',
      'phone' => '+390000001',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('password');
    $this->assertGuest();
  }

  public function test_register_rejects_unknown_place_of_birth(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'unknown-place@example.com',
      'password' => 'strong-pass-123',
      'password_confirmation' => 'strong-pass-123',
      'first_name' => 'Luca',
      'last_name' => 'Verdi',
      'date_of_birth' => '1988-11-02',
      'place_of_birth' => 'Atlantide',
      'gender' => 'M',
      'phone' => '+390000099',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('place_of_birth');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
      'username' => 'unknown-place@example.com',
    ]);
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

  public function test_login_accepts_email_when_form_invites_username_or_email(): void
  {
    $patient = User::create([
      'username' => 'patient',
      'email' => 'patient@example.com',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $patient->id, 'phone' => '555-0100']);

    $response = $this->post('/login', [
      'username' => 'patient@example.com',
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

  public function test_patient_can_update_profile_contact_fields(): void
  {
    $user = User::create([
      'username' => 'patient@example.com',
      'email' => 'patient@example.com',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $profile = PatientProfile::create([
      'user_id' => $user->id,
      'phone' => '555-0100',
      'address' => 'Via Roma 1',
      'identity_code' => 'RSSMRA80A01H501U',
    ]);

    $this->actingAs($user)
      ->patch('/patient/profile', [
        'email' => 'mario.rossi@example.com',
        'phone' => '555-0200',
        'address' => 'Via Milano 2',
      ])
      ->assertRedirect('/patient/profile');

    $this->assertSame('mario.rossi@example.com', $user->fresh()->email);
    $this->assertSame('555-0200', $profile->fresh()->phone);
    $this->assertSame('Via Milano 2', $profile->fresh()->address);
  }

  public function test_patient_can_change_password_from_profile(): void
  {
    $user = User::create([
      'username' => 'patient',
      'password' => Hash::make('old-password'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $user->id, 'phone' => '555-0100']);

    $this->actingAs($user)
      ->put('/patient/password', [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
      ])
      ->assertRedirect('/patient/profile');

    $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
  }
}
