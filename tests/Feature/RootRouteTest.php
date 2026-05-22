<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RootRouteTest extends TestCase
{
  use RefreshDatabase;

  public function test_guest_root_redirects_to_login(): void
  {
    $this->get('/')->assertRedirect('/login');
  }

  public function test_authenticated_patient_is_redirected_to_portal(): void
  {
    $this->actingAs($this->userWithRole(User::ROLE_PATIENT))
      ->get('/')
      ->assertRedirect('/patient');
  }

  public function test_authenticated_doctor_is_redirected_to_portal(): void
  {
    $this->actingAs($this->userWithRole(User::ROLE_DOCTOR))
      ->get('/')
      ->assertRedirect('/doctor/agenda');
  }

  public function test_authenticated_legacy_admin_is_redirected_to_unsupported_role(): void
  {
    $this->actingAs($this->userWithRole('admin'))
      ->get('/')
      ->assertRedirect('/unsupported-role');
  }

  private function userWithRole(string $role): User
  {
    return User::create([
      'username' => $role,
      'password' => Hash::make('password123'),
      'role' => $role,
    ]);
  }
}
