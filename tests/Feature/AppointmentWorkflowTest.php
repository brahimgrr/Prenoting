<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilitySlot;
use App\Models\ClinicLocation;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\Specialty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentWorkflowTest extends TestCase
{
  use RefreshDatabase;

  public function test_patient_can_book_available_slot_and_slot_is_marked_booked(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();

    $response = $this->actingAs($patientUser)->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
      'notes' => 'Prima visita',
    ]);

    $response->assertRedirect('/patient/appointments');
    $appointment = Appointment::first();
    $this->assertSame($patient->id, $appointment->patient_id);
    $this->assertSame($doctor->id, $appointment->doctor_id);
    $this->assertSame($clinic->id, $appointment->clinic_id);
    $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
    $this->assertTrue($slot->fresh()->is_booked);
  }

  public function test_booking_page_renders_service_week_days_and_available_slots(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $weekStart = $slot->start_at->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$slot->start_at->toDateString()}");

    $response->assertOk();
    $response->assertSee('Scegli la prestazione');
    $response->assertSee('Scegli il giorno');
    $response->assertSee("Scegli l'orario", false);
    $response->assertSee('Mese visualizzato');
    $response->assertDontSee('Mese precedente');
    $response->assertDontSee('Mese successivo');
    $response->assertSee($service->name);
    $response->assertSee($slot->start_at->format('H:i'));
    $response->assertSee('slot liberi');
  }

  public function test_booking_page_can_jump_to_a_far_month_with_availability(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $farStart = CarbonImmutable::now()->addMonths(3)->startOfMonth()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    $farSlot = AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $farStart,
      'end_at' => $farStart->addMinutes(30),
    ]);
    $month = $farSlot->start_at->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee(ucfirst($farSlot->start_at->locale('it')->isoFormat('MMMM YYYY')));
    $response->assertSee($farSlot->start_at->format('d'));
    $response->assertSee('month='.$month, false);
  }

  public function test_booking_month_selector_contains_direct_navigation_fallback(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $month = $slot->start_at->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee('onchange="window.location.href=this.value"', false);
  }

  public function test_booking_week_arrows_render_direct_navigation_links(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $weekStart = $slot->start_at->copy()->startOfWeek()->toDateString();
    $prevWeek = CarbonImmutable::parse($weekStart)->subWeek()->toDateString();
    $serviceId = $service->id;

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$slot->start_at->toDateString()}");

    $response->assertOk();
    $response->assertSee('data-week-url="http://127.0.0.1:8080/patient/book/week?service_id='.$serviceId.'&amp;week_start='.$prevWeek.'"', false);
    $response->assertSee('week-nav-arrow', false);
    $response->assertSee('href="http://127.0.0.1:8080/patient/book?service_id='.$serviceId.'&amp;week_start='.$prevWeek.'"', false);
  }

  public function test_booking_period_filter_shows_only_matching_slots(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $date = CarbonImmutable::parse($slot->start_at)->startOfDay()->addDay();
    $morningSlot = AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $date->setTime(9, 0),
      'end_at' => $date->setTime(9, 30),
    ]);
    $afternoonSlot = AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $date->setTime(15, 0),
      'end_at' => $date->setTime(15, 30),
    ]);
    $weekStart = $date->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date->toDateString()}&period=mattina");

    $response->assertOk();
    $response->assertSee($morningSlot->start_at->format('H:i'));
    $response->assertDontSee($afternoonSlot->start_at->format('H:i'));
    $response->assertSee('period=mattina', false);
  }

  public function test_booking_period_filter_keeps_controls_visible_when_no_slots_match(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $date = CarbonImmutable::parse($slot->start_at)->startOfDay()->addDay();
    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $date->setTime(15, 0),
      'end_at' => $date->setTime(15, 30),
    ]);
    $weekStart = $date->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$date->toDateString()}&period=mattina");

    $response->assertOk();
    $response->assertSee('Filtra orari');
    $response->assertSee('Nessuno slot disponibile');
    $response->assertSee('Mattina');
    $response->assertSee('Pomeriggio');
  }

  public function test_booking_month_jump_shows_days_from_selected_month_instead_of_previous_month(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $targetStart = CarbonImmutable::create(2030, 5, 1, 9, 0);
    AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $targetStart,
      'end_at' => $targetStart->addMinutes(30),
    ]);
    $month = $targetStart->format('Y-m');

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&month={$month}");

    $response->assertOk();
    $response->assertSee('data-date="2030-05-01"', false);
    $response->assertDontSee('data-date="2030-04-29"', false);
  }

  public function test_second_booking_attempt_for_same_slot_fails(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $otherUser = $this->patient('other-patient')[0];

    $this->actingAs($patientUser)->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
    ]);

    $response = $this->actingAs($otherUser)->from('/patient/book')->post('/appointments', [
      'slot_id' => $slot->id,
      'service_id' => $service->id,
    ]);

    $response->assertRedirect('/patient/book');
    $response->assertSessionHasErrors('slot_id');
    $this->assertSame(1, Appointment::count());
  }

  public function test_patient_can_cancel_future_confirmed_appointment_and_free_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);

    $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/cancel", [
      'cancellation_reason' => 'Non posso partecipare',
    ]);

    $response->assertRedirect('/patient/appointments');
    $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    $this->assertFalse($slot->fresh()->is_booked);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'previous_status' => Appointment::STATUS_CONFIRMED,
      'new_status' => Appointment::STATUS_CANCELLED,
      'changed_by' => $patientUser->id,
    ]);
  }

  public function test_patient_can_reschedule_to_different_available_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $oldSlot] = $this->bookingContext();
    $newSlot = $this->slot($doctor, $clinic, 30);
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $oldSlot);

    $response = $this->actingAs($patientUser)->post("/appointments/{$appointment->id}/reschedule", [
      'slot_id' => $newSlot->id,
    ]);

    $response->assertRedirect('/patient/appointments');
    $appointment->refresh();
    $this->assertSame($newSlot->id, $appointment->slot_id);
    $this->assertFalse($oldSlot->fresh()->is_booked);
    $this->assertTrue($newSlot->fresh()->is_booked);
    $this->assertDatabaseHas('appointment_status_history', [
      'appointment_id' => $appointment->id,
      'new_status' => 'rescheduled',
    ]);
  }

  public function test_selected_booking_slot_renders_confirmation_before_posting(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $weekStart = $slot->start_at->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/patient/book?service_id={$service->id}&week_start={$weekStart}&date={$slot->start_at->toDateString()}&slot_id={$slot->id}");

    $response->assertOk();
    $response->assertSee('Conferma prenotazione');
    $response->assertSee($doctor->display_name);
    $response->assertSee($clinic->name);
    $response->assertSee('name="slot_id" value="'.$slot->id.'"', false);
    $response->assertSee('action="/appointments"', false);
    $response->assertSee('Annulla prenotazione');
    $response->assertSee('#booking-step-service', false);
    $response->assertDontSee('<button type="submit" class="slot-time-button"', false);
  }

  public function test_patient_appointments_page_renders_cancel_confirmation_modal(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);

    $response = $this->actingAs($patientUser)->get('/patient/appointments');

    $response->assertOk();
    $response->assertSee("cancelAppointmentModal{$appointment->id}", false);
    $response->assertSee('Si, annulla');
    $response->assertSee("/appointments/{$appointment->id}/cancel", false);
  }

  public function test_patient_can_open_reschedule_wizard_and_confirm_new_slot(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $oldSlot] = $this->bookingContext();
    $newSlot = $this->slot($doctor, $clinic, 48);
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $oldSlot);
    $weekStart = $newSlot->start_at->copy()->startOfWeek()->toDateString();

    $response = $this->actingAs($patientUser)->get("/appointments/{$appointment->id}/edit?week_start={$weekStart}&date={$newSlot->start_at->toDateString()}&slot_id={$newSlot->id}");

    $response->assertOk();
    $response->assertSee('Stai riprogrammando');
    $response->assertSee($service->name);
    $response->assertSee($newSlot->start_at->format('H:i'));
    $response->assertSee('Conferma spostamento');
    $response->assertSee("/appointments/{$appointment->id}/reschedule", false);
  }

  public function test_patient_cannot_open_another_patients_reschedule_wizard(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $otherUser = $this->patient('other-patient')[0];
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot);

    $this->actingAs($otherUser)->get("/appointments/{$appointment->id}/edit")->assertNotFound();
  }

  public function test_patient_cannot_cancel_non_confirmed_or_past_appointment(): void
  {
    [$patientUser, $patient, $doctor, $service, $clinic, $slot] = $this->bookingContext();
    $appointment = $this->appointment($patient, $doctor, $service, $clinic, $slot, Appointment::STATUS_COMPLETED);

    $response = $this->actingAs($patientUser)->from('/patient/appointments')->post("/appointments/{$appointment->id}/cancel");

    $response->assertRedirect('/patient/appointments');
    $response->assertSessionHasErrors('status');
    $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    $this->assertSame(0, AppointmentStatusHistory::count());
  }

  private function bookingContext(): array
  {
    [$patientUser, $patient] = $this->patient('patient');
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
    $service = MedicalService::create(['name' => 'Visita cardiologica', 'specialty_id' => $specialty->id]);
    DoctorService::create(['doctor_id' => $doctor->id, 'service_id' => $service->id]);
    $clinic = ClinicLocation::create(['name' => 'Ambulatorio Centro', 'address' => 'Via Roma 1']);
    $slot = $this->slot($doctor, $clinic, 24);

    return [$patientUser, $patient, $doctor, $service, $clinic, $slot];
  }

  private function patient(string $username): array
  {
    $user = User::create([
      'username' => $username,
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);
    $profile = PatientProfile::create(['user_id' => $user->id, 'phone' => '555-0100']);

    return [$user, $profile];
  }

  private function slot(DoctorProfile $doctor, ClinicLocation $clinic, int $offsetHours): AvailabilitySlot
  {
    $start = CarbonImmutable::now()->addHours($offsetHours);

    return AvailabilitySlot::create([
      'doctor_id' => $doctor->id,
      'clinic_id' => $clinic->id,
      'start_at' => $start,
      'end_at' => $start->addMinutes(30),
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
    $slot->forceFill(['is_booked' => true])->save();

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
