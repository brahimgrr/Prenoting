<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilitySlot;
use App\Models\ClinicLocation;
use App\Models\DoctorProfile;
use App\Models\DoctorTreatmentOffering;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\Specialty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
  use RefreshDatabase;

  public function test_doctor_sees_only_own_schedule_and_can_update_allowed_status(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor/schedule')
      ->assertOk()
      ->assertSee('Mario Rossi')
      ->assertDontSee('Altro Paziente');

    $this->actingAs($doctorUser)
      ->post("/doctor/appointments/{$appointment->id}/status", ['status' => Appointment::STATUS_CHECKED_IN])
      ->assertRedirect('/doctor/schedule');

    $this->assertSame(Appointment::STATUS_CHECKED_IN, $appointment->fresh()->status);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'changed_by' => $doctorUser->id,
      'new_status' => Appointment::STATUS_CHECKED_IN,
    ]);
  }

  public function test_doctor_can_block_and_unblock_own_future_availability_slot(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $slot = AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $appointment->clinic_id,
      'start_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 0),
      'end_at' => CarbonImmutable::now()->addDays(3)->setTime(10, 30),
    ]);

    $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post("/doctor/availability/{$slot->id}/block")
      ->assertRedirect('/doctor/schedule');

    $this->assertTrue($slot->fresh()->is_blocked);

    $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post("/doctor/availability/{$slot->id}/unblock")
      ->assertRedirect('/doctor/schedule');

    $this->assertFalse($slot->fresh()->is_blocked);
  }

  public function test_doctor_can_preview_batch_availability_with_creatable_and_skipped_counts(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $appointment->clinic_id,
      'start_at' => $startDate->setTime(9, 0),
      'end_at' => $startDate->setTime(9, 30),
    ]);

    $response = $this->actingAs($doctorUser)->get('/doctor/availability/preview?'.http_build_query([
      'start_date' => $startDate->toDateString(),
      'end_date' => $startDate->toDateString(),
      'weekdays' => [$startDate->dayOfWeekIso],
      'start_time' => '09:00',
      'end_time' => '10:00',
      'slot_duration' => 30,
      'clinic_id' => $appointment->clinic_id,
    ]));

    $response->assertOk();
    $response->assertSee('Anteprima disponibilita');
    $response->assertSee('1 slot creabile');
    $response->assertSee('1 saltato');
    $response->assertSee('Ambulatorio Centro');
    $response->assertSee('09:30');
  }

  public function test_doctor_can_create_batch_availability_without_duplicates(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $startDate = CarbonImmutable::now()->next(CarbonImmutable::TUESDAY);
    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $appointment->clinic_id,
      'start_at' => $startDate->setTime(14, 0),
      'end_at' => $startDate->setTime(14, 30),
    ]);

    $this->actingAs($doctorUser)
      ->post('/doctor/availability/batch', [
        'start_date' => $startDate->toDateString(),
        'end_date' => $startDate->toDateString(),
        'weekdays' => [$startDate->dayOfWeekIso],
        'start_time' => '14:00',
        'end_time' => '15:00',
        'slot_duration' => 30,
        'clinic_id' => $appointment->clinic_id,
      ])
      ->assertRedirect('/doctor/schedule');

    $this->assertDatabaseHas('availability_slots', [
      'doctor_id' => $doctor->id,
      'clinic_id' => $appointment->clinic_id,
      'start_at' => $startDate->setTime(14, 30)->toDateTimeString(),
    ]);
    $this->assertSame(
      2,
      AvailabilitySlot::where('doctor_id', $doctor->id)
        ->whereDate('start_at', $startDate->toDateString())
        ->whereTime('start_at', '>=', '14:00')
        ->whereTime('start_at', '<', '15:00')
        ->count(),
    );
  }

  public function test_doctor_cannot_create_batch_availability_in_the_past(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $pastDate = CarbonImmutable::now()->subWeeks(2)->startOfWeek();

    $response = $this->actingAs($doctorUser)
      ->from('/doctor/schedule')
      ->post('/doctor/availability/batch', [
        'start_date' => $pastDate->toDateString(),
        'end_date' => $pastDate->toDateString(),
        'weekdays' => [$pastDate->dayOfWeekIso],
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_duration' => 30,
        'clinic_id' => $appointment->clinic_id,
      ]);

    $response->assertRedirect('/doctor/schedule');
    $response->assertSessionHasErrors('availability');
  }

  public function test_doctor_cannot_block_another_doctors_availability_slot(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $otherDoctor = DoctorProfile::whereKeyNot($doctor->id)->firstOrFail();
    $slot = AvailabilitySlot::create([
      'doctor_id' => $otherDoctor->id,
      'clinic_id' => $appointment->clinic_id,
      'start_at' => CarbonImmutable::now()->addDays(3)->setTime(11, 0),
      'end_at' => CarbonImmutable::now()->addDays(3)->setTime(11, 30),
    ]);

    $this->actingAs($doctorUser)
      ->post("/doctor/availability/{$slot->id}/block")
      ->assertNotFound();

    $this->assertFalse($slot->fresh()->is_blocked);
  }

  public function test_doctor_can_manage_own_treatment_offerings(): void
  {
    [$doctorUser, $doctor] = $this->dashboardContext();

    $this->actingAs($doctorUser)
      ->get('/doctor/treatments')
      ->assertOk()
      ->assertSee('Trattamenti');

    $this->actingAs($doctorUser)
      ->post('/doctor/treatments', [
        'name' => 'Holter cardiaco',
        'category' => MedicalService::CATEGORY_EXAM,
        'specialty_id' => $doctor->specialty_id,
        'duration_minutes' => 45,
        'price' => '95.50',
        'is_active' => '1',
      ])
      ->assertRedirect('/doctor/treatments');

    $offering = DoctorTreatmentOffering::where('doctor_id', $doctor->id)
      ->where('name', 'Holter cardiaco')
      ->firstOrFail();

    $this->actingAs($doctorUser)
      ->get("/doctor/treatments/{$offering->id}/edit")
      ->assertOk()
      ->assertSee('Modifica trattamento');

    $this->actingAs($doctorUser)
      ->patch("/doctor/treatments/{$offering->id}", [
        'name' => 'Holter pressorio',
        'category' => MedicalService::CATEGORY_EXAM,
        'specialty_id' => $doctor->specialty_id,
        'duration_minutes' => 60,
        'price' => '110.00',
        'is_active' => '1',
      ])
      ->assertRedirect('/doctor/treatments');

    $this->assertDatabaseHas('doctor_treatment_offerings', [
      'id' => $offering->id,
      'doctor_id' => $doctor->id,
      'name' => 'Holter pressorio',
      'duration_minutes' => 60,
    ]);

    $this->actingAs($doctorUser)
      ->patch("/doctor/treatments/{$offering->id}/status", ['is_active' => '0'])
      ->assertRedirect('/doctor/treatments');

    $this->assertFalse($offering->fresh()->is_active);
  }

  public function test_doctor_cannot_edit_another_doctors_treatment_offering(): void
  {
    [$doctorUser, $doctor] = $this->dashboardContext();
    $otherDoctor = DoctorProfile::whereKeyNot($doctor->id)->firstOrFail();
    $offering = DoctorTreatmentOffering::create([
      'doctor_id' => $otherDoctor->id,
      'name' => 'Trattamento privato',
      'category' => MedicalService::CATEGORY_VISIT,
      'specialty_id' => $otherDoctor->specialty_id,
      'duration_minutes' => 30,
      'price' => '80.00',
      'is_active' => true,
    ]);

    $this->actingAs($doctorUser)
      ->get("/doctor/treatments/{$offering->id}/edit")
      ->assertNotFound();

    $this->actingAs($doctorUser)
      ->patch("/doctor/treatments/{$offering->id}", [
        'name' => 'Tentativo',
        'category' => MedicalService::CATEGORY_VISIT,
        'specialty_id' => $doctor->specialty_id,
        'duration_minutes' => 30,
        'price' => '80.00',
        'is_active' => '1',
      ])
      ->assertNotFound();
  }

  public function test_staff_can_filter_and_cancel_future_appointment_freeing_slot(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext();
    $staff = User::create([
      'username' => 'staff',
      'password' => Hash::make('staff123'),
      'role' => User::ROLE_STAFF,
    ]);

    $this->actingAs($staff)
      ->get('/staff/appointments?status=confirmed')
      ->assertOk()
      ->assertSee('Mario Rossi');

    $this->actingAs($staff)
      ->post("/staff/appointments/{$appointment->id}/status", ['status' => Appointment::STATUS_CANCELLED])
      ->assertRedirect('/staff/appointments');

    $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    $this->assertFalse($appointment->slot->fresh()->is_booked);
  }

  public function test_terminal_states_cannot_be_resurrected(): void
  {
    [$doctorUser, $doctor, $appointment] = $this->dashboardContext(Appointment::STATUS_COMPLETED);
    $staff = User::create([
      'username' => 'staff',
      'password' => Hash::make('staff123'),
      'role' => User::ROLE_STAFF,
    ]);

    $response = $this->actingAs($staff)
      ->from('/staff/appointments')
      ->post("/staff/appointments/{$appointment->id}/status", ['status' => Appointment::STATUS_CHECKED_IN]);

    $response->assertRedirect('/staff/appointments');
    $response->assertSessionHasErrors('status');
    $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    $this->assertSame(0, AppointmentStatusHistory::count());
  }

  public function test_patient_cannot_access_doctor_or_staff_routes(): void
  {
    $patientUser = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);

    $this->actingAs($patientUser)->get('/doctor')->assertForbidden();
    $this->actingAs($patientUser)->get('/staff')->assertForbidden();
  }

  private function dashboardContext(string $status = Appointment::STATUS_CONFIRMED): array
  {
    $specialty = Specialty::create(['name' => 'Cardiologia']);
    $doctorUser = User::create([
      'username' => 'doctor.heart',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $doctor = DoctorProfile::create([
      'user_id' => $doctorUser->id,
      'display_name' => 'Dott.ssa Amelia Cuori',
      'specialty_id' => $specialty->id,
    ]);
    $otherDoctorUser = User::create([
      'username' => 'doctor.skin',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    $otherDoctor = DoctorProfile::create([
      'user_id' => $otherDoctorUser->id,
      'display_name' => 'Dott. Dorian Pelle',
      'specialty_id' => $specialty->id,
    ]);
    $patientUser = User::create([
      'username' => 'patient',
      'first_name' => 'Mario',
      'last_name' => 'Rossi',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $patient = PatientProfile::create(['user_id' => $patientUser->id, 'phone' => '555-0100']);
    $otherPatientUser = User::create([
      'username' => 'other',
      'first_name' => 'Altro',
      'last_name' => 'Paziente',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $otherPatient = PatientProfile::create(['user_id' => $otherPatientUser->id, 'phone' => '555-0101']);
    $service = MedicalService::create(['name' => 'Visita cardiologica', 'specialty_id' => $specialty->id]);
    $clinic = ClinicLocation::create(['name' => 'Ambulatorio Centro', 'address' => 'Via Roma 1']);
    $slot = $this->slot($doctor, $clinic, 24);
    $otherSlot = $this->slot($otherDoctor, $clinic, 25);
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot, $status);
    $this->appointment($otherPatient, $otherDoctor, $service, $clinic, $otherSlot);

    return [$doctorUser, $doctor, $appointment];
  }

  private function slot(DoctorProfile $doctor, ClinicLocation $clinic, int $offsetHours): AvailabilitySlot
  {
    $start = CarbonImmutable::now()->addHours($offsetHours);

    return AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
      'is_booked' => true,
    ]);
  }

  private function appointment(
    PatientProfile $patient,
    DoctorProfile $doctor,
    MedicalService $service,
    ClinicLocation $clinic,
    AvailabilitySlot $slot,
    string $status = Appointment::STATUS_CONFIRMED,
  ): Appointment {
    return Appointment::create([
      'patient_id' => $patient->id,
      'doctor_id' => $doctor->id,
      'service_id' => $service->id,
      'clinic_id' => $clinic->id,
      'slot_id' => $slot->id,
      'start_at' => $slot->start_at,
      'end_at' => $slot->end_at,
      'status' => $status,
    ]);
  }
}
