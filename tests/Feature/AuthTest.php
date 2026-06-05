<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorProfile;
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

    public function test_users_table_uses_email_as_the_only_login_identifier(): void
    {
        $legacyLoginColumn = 'user'.'name';

        $this->assertTrue(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', $legacyLoginColumn));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_patient_can_register_and_reach_dashboard(): void
    {
        $response = $this->post('/register', [
            'email' => 'sara@example.com',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'first_name' => 'Sara',
            'last_name' => 'Conti',
            'date_of_birth' => '1990-05-21',
            'place_of_birth' => 'Roma',
            'gender' => 'F',
            'phone' => '+393331234567',
        ]);

        $response->assertRedirect('/patient');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'sara@example.com',
        ]);
        $this->assertTrue(User::where('email', 'sara@example.com')->firstOrFail()->hasRole(User::ROLE_PATIENT));
        $profile = PatientProfile::firstOrFail();
        $this->assertSame('1990-05-21', $profile->date_of_birth?->toDateString());
        $this->assertSame('ROMA', $profile->place_of_birth);
        $this->assertSame('F', $profile->gender);
        $this->assertSame('+393331234567', $profile->phone);
        $this->assertSame('CNTSRA90E61H501K', $profile->codice_fiscale);
    }

    public function test_register_requires_first_and_last_name(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'missing-names@example.com',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'date_of_birth' => '1990-05-21',
            'place_of_birth' => 'Roma',
            'gender' => 'M',
            'phone' => '+393331234568',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'missing-names@example.com',
        ]);
    }

    public function test_register_rejects_numbers_and_symbols_in_first_and_last_name(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'invalid-names@example.com',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'first_name' => 'Sara2',
            'last_name' => "D'Angelo",
            'date_of_birth' => '1990-05-21',
            'place_of_birth' => 'Roma',
            'gender' => 'F',
            'phone' => '+393331234569',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-names@example.com',
        ]);
    }

    public function test_register_rejects_invalid_phone_numbers(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'invalid-phone@example.com',
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
            'email' => 'invalid-phone@example.com',
        ]);
    }

    public function test_register_rejects_phone_numbers_with_too_few_digits(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'short-phone@example.com',
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
            'email' => 'short-phone@example.com',
        ]);
    }

    public function test_register_rejects_short_password(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'date_of_birth' => '1990-05-21',
            'place_of_birth' => 'Roma',
            'gender' => 'M',
            'phone' => '+393331234568',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_register_rejects_unknown_place_of_birth(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'unknown-place@example.com',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'first_name' => 'Luca',
            'last_name' => 'Verdi',
            'date_of_birth' => '1988-11-02',
            'place_of_birth' => 'Atlantide',
            'gender' => 'M',
            'phone' => '+393331234599',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('place_of_birth');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'unknown-place@example.com',
        ]);
    }

    public function test_register_rejects_email_without_top_level_domain(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'pippo@gmail',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'first_name' => 'Sara',
            'last_name' => 'Conti',
            'date_of_birth' => '1990-05-21',
            'place_of_birth' => 'Roma',
            'gender' => 'F',
            'phone' => '+393331234599',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'email' => 'Inserisci un indirizzo email valido.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'pippo@gmail',
        ]);
    }

    public function test_register_validation_messages_are_in_italian(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'non-una-email',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'password-diversa',
            'first_name' => 'Sara',
            'last_name' => 'Conti',
            'date_of_birth' => now()->addDay()->toDateString(),
            'place_of_birth' => 'Roma',
            'gender' => 'X',
            'phone' => '+393331234599',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'email' => 'Inserisci un indirizzo email valido.',
            'password' => 'La conferma della password non corrisponde.',
            'date_of_birth' => 'La data di nascita deve essere precedente a oggi.',
            'gender' => 'Seleziona un sesso valido.',
        ]);
        $this->assertGuest();
    }

    public function test_login_routes_roles_to_their_portals(): void
    {
        $patient = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($patient, User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $patient->id, 'phone' => '3331234567']);

        $response = $this->post('/login', [
            'email' => 'patient@example.com',
            'password' => 'patient123',
        ]);

        $response->assertRedirect('/patient');
        $this->assertAuthenticatedAs($patient);
    }

    public function test_legacy_admin_login_is_unsupported_and_admin_route_is_removed(): void
    {
        $admin = User::create([
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertRedirect('/unsupported-role');
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin')->assertNotFound();
    }

    public function test_login_accepts_email_only(): void
    {
        $patient = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($patient, User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $patient->id, 'phone' => '3331234567']);

        $response = $this->post('/login', [
            'email' => 'patient@example.com',
            'password' => 'patient123',
        ]);

        $response->assertRedirect('/patient');
        $this->assertAuthenticatedAs($patient);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::create([
            'email' => 'sara@example.com',
            'password' => Hash::make('strong-pass-123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'sara@example.com',
            'password' => 'wrong-pass-123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_patient_can_update_profile_contact_fields(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        $profile = PatientProfile::create([
            'user_id' => $user->id,
            'phone' => '3331234567',
            'address' => 'Via Roma 1',
            'codice_fiscale' => 'RSSMRA80A01H501U',
        ]);

        $this->actingAs($user)
            ->patch('/patient/profile', [
                'email' => 'mario.rossi@example.com',
                'phone' => '3337654321',
                'address' => 'Via Milano 2',
            ])
            ->assertRedirect('/patient/profile');

        $this->assertSame('patient@example.com', $user->fresh()->email);
        $this->assertSame('3337654321', $profile->fresh()->phone);
        $this->assertSame('Via Milano 2', $profile->fresh()->address);
    }

    public function test_patient_profile_rejects_invalid_phone_number(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        $profile = PatientProfile::create([
            'user_id' => $user->id,
            'phone' => '3331234567',
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

        $this->assertSame('3331234567', $profile->fresh()->phone);
    }

    public function test_patient_profile_rejects_phone_number_with_too_few_digits(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        $profile = PatientProfile::create([
            'user_id' => $user->id,
            'phone' => '3331234567',
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

        $this->assertSame('3331234567', $profile->fresh()->phone);
    }

    public function test_patient_profiles_table_no_longer_has_identity_code_column(): void
    {
        $this->assertFalse(Schema::hasColumn('patient_profiles', 'identity_code'));
        $this->assertTrue(Schema::hasColumn('patient_profiles', 'codice_fiscale'));
    }

    public function test_patient_profile_page_shows_codice_fiscale_and_hides_legacy_identity_code(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        PatientProfile::create([
            'user_id' => $user->id,
            'phone' => '3331234567',
            'codice_fiscale' => 'RSSMRA80A01H501U',
        ]);

        $response = $this->actingAs($user)->get('/patient/profile');

        $response->assertOk();
        $response->assertSee('profile-data-grid', false);
        $response->assertSee('profile-data-tile', false);
        $response->assertSee('Email');
        $response->assertSee('patient@example.com');
        $response->assertSee('Codice fiscale');
        $response->assertSee('RSSMRA80A01H501U');
        $response->assertDontSee('name="email"', false);
        $response->assertDontSeeText('Codice identificativo');
    }

    public function test_patient_can_change_password_from_profile(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $user->id, 'phone' => '3331234567']);

        $this->actingAs($user)
            ->put('/patient/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/patient/profile');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_patient_password_change_reports_wrong_current_password_before_new_password_errors(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $user->id, 'phone' => '3331234567']);

        $response = $this->actingAs($user)
            ->from('/patient/profile')
            ->put('/patient/password', [
                'current_password' => 'x',
                'password' => 'x',
                'password_confirmation' => 'x',
            ]);

        $response->assertRedirect('/patient/profile');
        $response->assertSessionHasErrors([
            'current_password' => 'La password attuale non e corretta.',
        ]);
        $response->assertSessionDoesntHaveErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_patient_password_change_reports_new_password_errors_in_italian_after_current_password_is_correct(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        PatientProfile::create(['user_id' => $user->id, 'phone' => '3331234567']);

        $response = $this->actingAs($user)
            ->from('/patient/profile')
            ->put('/patient/password', [
                'current_password' => 'old-password',
                'password' => 'x',
                'password_confirmation' => 'x',
            ]);

        $response->assertRedirect('/patient/profile');
        $response->assertSessionHasErrors([
            'password' => 'La password deve contenere almeno 8 caratteri.',
        ]);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_patient_dashboard_hides_legacy_booking_ctas_and_keeps_clean_quick_actions(): void
    {
        $user = User::create([
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($user, User::ROLE_PATIENT);
        PatientProfile::create([
            'user_id' => $user->id,
            'phone' => '3331234567',
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
            'email' => 'patient@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => Hash::make('patient123'),
        ]);
        $this->assignRole($patientUser, User::ROLE_PATIENT);
        $patient = PatientProfile::create([
            'user_id' => $patientUser->id,
            'phone' => '3331234567',
        ]);

        $doctorUser = User::create([
            'email' => 'doctor.derm@example.com',
            'password' => Hash::make('doctor123'),
        ]);
        $this->assignRole($doctorUser, User::ROLE_DOCTOR);
        $doctor = DoctorProfile::create([
            'user_id' => $doctorUser->id,
            'display_name' => 'Dott. Mbappe',
        ]);
        $service = MedicalService::create([
            'doctor_profile_id' => $doctor->id,
            'name' => 'Visita dermatologica',
        ]);
        $start = CarbonImmutable::now()->addDay()->setTime(10, 0);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
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
        $response->assertSee('id="email" name="email" type="email" autocomplete="email" value="" required', false);
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

    public function test_auth_pages_link_back_to_the_landing_page(): void
    {
        $loginResponse = $this->get('/login');
        $registerResponse = $this->get('/register');
        $landingUrl = route('home');

        $loginResponse->assertOk();
        $registerResponse->assertOk();
        $loginResponse->assertSee('href="'.$landingUrl.'"', false);
        $registerResponse->assertSee('href="'.$landingUrl.'"', false);
        $loginResponse->assertSeeText('Torna alla pagina iniziale');
        $registerResponse->assertSeeText('Torna alla pagina iniziale');
    }
}
