<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
    $profile = PatientProfile::firstOrFail();
    $this->assertSame('1990-05-21', $profile->date_of_birth?->toDateString());
    $this->assertSame('Roma', $profile->place_of_birth);
    $this->assertSame('F', $profile->gender);
    $this->assertSame('+390000000', $profile->phone);
    $this->assertSame('CNTSRA90E61H501K', $profile->codice_fiscale);
  }

  public function test_register_requires_first_and_last_name(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'missing-names@example.com',
      'password' => 'strong-pass-123',
      'password_confirmation' => 'strong-pass-123',
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'M',
      'phone' => '+390000001',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors(['first_name', 'last_name']);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
      'username' => 'missing-names@example.com',
    ]);
  }

  public function test_register_rejects_numbers_and_symbols_in_first_and_last_name(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'invalid-names@example.com',
      'password' => 'strong-pass-123',
      'password_confirmation' => 'strong-pass-123',
      'first_name' => 'Sara2',
      'last_name' => "D'Angelo",
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'F',
      'phone' => '+390000002',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors(['first_name', 'last_name']);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
      'username' => 'invalid-names@example.com',
    ]);
  }

  public function test_register_rejects_invalid_phone_numbers(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'invalid-phone@example.com',
      'password' => 'strong-pass-123',
      'password_confirmation' => 'strong-pass-123',
      'first_name' => 'Sara',
      'last_name' => 'Conti',
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'F',
      'phone' => 'telefono',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
      'username' => 'invalid-phone@example.com',
    ]);
  }

  public function test_register_rejects_phone_numbers_with_too_few_digits(): void
  {
    $response = $this->from('/register')->post('/register', [
      'username' => 'short-phone@example.com',
      'password' => 'strong-pass-123',
      'password_confirmation' => 'strong-pass-123',
      'first_name' => 'Sara',
      'last_name' => 'Conti',
      'date_of_birth' => '1990-05-21',
      'place_of_birth' => 'Roma',
      'gender' => 'F',
      'phone' => '+39',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
      'username' => 'short-phone@example.com',
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

  public function test_legacy_admin_login_is_unsupported_and_admin_route_is_removed(): void
  {
    $admin = User::create([
      'username' => 'admin',
      'password' => Hash::make('admin123'),
      'role' => 'admin',
    ]);

    $this->post('/login', [
      'username' => 'admin',
      'password' => 'admin123',
    ])->assertRedirect('/unsupported-role');
    $this->assertAuthenticatedAs($admin);

    $this->get('/admin')->assertNotFound();
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
      'codice_fiscale' => 'RSSMRA80A01H501U',
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

  public function test_patient_profile_rejects_invalid_phone_number(): void
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
    ]);

    $this->actingAs($user)
      ->from('/patient/profile')
      ->patch('/patient/profile', [
        'email' => 'mario.rossi@example.com',
        'phone' => 'telefono',
        'address' => 'Via Milano 2',
      ])
      ->assertRedirect('/patient/profile')
      ->assertSessionHasErrors('phone');

    $this->assertSame('555-0100', $profile->fresh()->phone);
  }

  public function test_patient_profile_rejects_phone_number_with_too_few_digits(): void
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
    ]);

    $this->actingAs($user)
      ->from('/patient/profile')
      ->patch('/patient/profile', [
        'email' => 'mario.rossi@example.com',
        'phone' => '+39',
        'address' => 'Via Milano 2',
      ])
      ->assertRedirect('/patient/profile')
      ->assertSessionHasErrors('phone');

    $this->assertSame('555-0100', $profile->fresh()->phone);
  }

  public function test_patient_profiles_table_no_longer_has_identity_code_column(): void
  {
    $this->assertFalse(Schema::hasColumn('patient_profiles', 'identity_code'));
    $this->assertTrue(Schema::hasColumn('patient_profiles', 'codice_fiscale'));
  }

  public function test_patient_profile_page_shows_codice_fiscale_and_hides_legacy_identity_code(): void
  {
    $user = User::create([
      'username' => 'patient@example.com',
      'email' => 'patient@example.com',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create([
      'user_id' => $user->id,
      'phone' => '555-0100',
      'codice_fiscale' => 'RSSMRA80A01H501U',
    ]);

    $response = $this->actingAs($user)->get('/patient/profile');

    $response->assertOk();
    $response->assertSee('class="profile-data-grid"', false);
    $response->assertSee('class="profile-data-tile"', false);
    $response->assertSee('Codice fiscale');
    $response->assertSee('RSSMRA80A01H501U');
    $response->assertDontSee(' readonly', false);
    $response->assertDontSeeText('Codice identificativo');
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

  public function test_patient_dashboard_hides_legacy_booking_ctas_and_keeps_clean_quick_actions(): void
  {
    $user = User::create([
      'username' => 'patient@example.com',
      'email' => 'patient@example.com',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create([
      'user_id' => $user->id,
      'phone' => '555-0100',
    ]);

    $response = $this->actingAs($user)->get('/patient');

    $response->assertOk();
    $response->assertDontSee('/patient/book?mode=doctor', false);
    $response->assertSeeText('Prenota visita');
    $response->assertSeeText('I miei appuntamenti');
  }

  public function test_patient_dashboard_shows_status_label_instead_of_empty_badge(): void
  {
    $patientUser = User::create([
      'username' => 'patient@example.com',
      'email' => 'patient@example.com',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $patient = PatientProfile::create([
      'user_id' => $patientUser->id,
      'phone' => '555-0100',
    ]);

    $doctorUser = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = \App\Models\DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott. Mbappe',
    ]);
    $service = MedicalService::create([
      'name' => 'Visita dermatologica',
    ]);
    $start = CarbonImmutable::now()->addDay()->setTime(10, 0);
    $appointment = Appointment::create([
      'patient_id' => $patient->id,
      'doctor_profile_id' => $doctor->id,
      'service_id' => $service->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
      'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $response = $this->actingAs($patientUser)->get('/patient');
    $appointmentTime = $appointment->start_at->format('d/m/Y H:i').' - '.$appointment->end_at->format('H:i');

    $response->assertOk();
    $this->assertSame(1, substr_count($response->getContent(), $appointmentTime));
    $response->assertDontSeeText('Confermato');
    $response->assertDontSeeText('Medico');
    $response->assertDontSeeText('Ambulatorio');
    $response->assertDontSee('<span class="badge rounded-pill text-bg-success">

</span>', false);
  }

  public function test_register_page_shows_comuni_suggestions_below_the_input(): void
  {
    $response = $this->get('/register');

    $response->assertOk();
    $response->assertSee('name="first_name"', false);
    $response->assertSee('name="last_name"', false);
    $response->assertSee('id="first_name" name="first_name" autocomplete="given-name" value="" required', false);
    $response->assertSee('id="last_name" name="last_name" autocomplete="family-name" value="" required', false);
    $response->assertSee('data-comune-combobox', false);
    $response->assertSee('data-comune-input', false);
    $response->assertSee('data-comune-suggestions', false);
    $response->assertSee('data-comune-option value="ROMA"', false);
    $response->assertSee('data-comune-option value="MILANO"', false);
    $response->assertSee('data-comune-option value="FRANCIA"', false);
    $response->assertDontSee('<datalist', false);
    $response->assertDontSee('list="comuni-list"', false);
  }
}
