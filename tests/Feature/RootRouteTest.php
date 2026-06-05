<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RootRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_shows_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('La salute della tua pelle');
    }

    public function test_guest_root_shows_active_doctor_real_data(): void
    {
        $doctor = $this->createDoctorProfile([
            'email' => 'doctor.derm@example.com',
            'display_name' => 'Dott.ssa Giulia Ferretti',
            'phone' => '3331000000',
            'clinic_address' => 'Via Roma 1, Milano',
            'license_number' => 'DERM-001',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Il nostro medico');
        $response->assertSee($doctor->display_name);
        $response->assertSee('doctor.derm@example.com');
        $response->assertSee('3331000000');
        $response->assertSee('Via Roma 1, Milano');
        $response->assertSee('DERM-001');
    }

    public function test_guest_root_omits_missing_doctor_fields(): void
    {
        $this->createDoctorProfile([
            'display_name' => 'Dott. Marco Bianchi',
            'phone' => '',
            'clinic_address' => '',
            'license_number' => '',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Il nostro medico');
        $response->assertSee('Dott. Marco Bianchi');
        $response->assertDontSee('<dt>Telefono</dt>', false);
        $response->assertDontSee('<dt>Studio</dt>', false);
        $response->assertDontSee('<dt>Numero iscrizione</dt>', false);
        $response->assertDontSee('Non indicato');
    }

    public function test_guest_root_hides_doctor_section_without_active_doctor(): void
    {
        $this->createDoctorProfile([
            'display_name' => 'Dott. Non Attivo',
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Il nostro medico')
            ->assertDontSee('Dott. Non Attivo');
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
            ->assertRedirect('/doctor');
    }

    public function test_authenticated_legacy_admin_is_redirected_to_unsupported_role(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/')
            ->assertRedirect('/unsupported-role');
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'email' => $role.'@example.com',
            'password' => Hash::make('password123'),
        ]);

        if (in_array($role, [User::ROLE_PATIENT, User::ROLE_DOCTOR], true)) {
            $this->assignRole($user, $role);
        }

        return $user;
    }

    private function createDoctorProfile(array $attributes = []): DoctorProfile
    {
        $user = User::create([
            'email' => $attributes['email'] ?? 'doctor@example.com',
            'password' => Hash::make('password123'),
            'is_active' => $attributes['is_active'] ?? true,
        ]);
        $this->assignRole($user, User::ROLE_DOCTOR);

        return DoctorProfile::create([
            'user_id' => $user->id,
            'display_name' => $attributes['display_name'] ?? 'Dott. Test',
            'phone' => $attributes['phone'] ?? '3330000000',
            'clinic_address' => $attributes['clinic_address'] ?? 'Via Test 1',
            'license_number' => $attributes['license_number'] ?? 'TEST-001',
        ]);
    }
}
