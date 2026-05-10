# Appointment 24h Change Lock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Block patient cancellation and rescheduling during the 24 hours before an appointment.

**Architecture:** Keep the rule in `AppointmentService::validateFutureConfirmed()` so both patient actions share the same server-side enforcement. Add a small UI guard in the patient appointment card so unavailable actions are not offered inside the cutoff.

**Tech Stack:** Laravel, Blade, PHPUnit feature tests, Carbon application time helpers.

---

### Task 1: Server-side 24h cutoff

**Files:**
- Modify: `tests/Feature/AppointmentWorkflowTest.php`
- Modify: `app/Services/AppointmentService.php`

- [x] **Step 1: Write failing cancellation test**

Add this test near the existing cancellation tests:

```php
public function test_patient_cannot_cancel_appointment_within_24_hours(): void
{
  CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-10 09:00:00'));
  [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
  $slot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
  $appointment = $this->appointment($patient, $doctor, $service, $slot);

  $response = $this->actingAs($patientUser)
    ->from('/patient/appointments')
    ->post("/appointments/{$appointment->id}/cancel");

  $response->assertRedirect('/patient/appointments');
  $response->assertSessionHasErrors('start_at');
  $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->fresh()->status);
  CarbonImmutable::setTestNow();
}
```

- [x] **Step 2: Write failing reschedule test**

Add this test near the existing reschedule tests:

```php
public function test_patient_cannot_reschedule_appointment_within_24_hours(): void
{
  CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-10 09:00:00'));
  [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
  $oldSlot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
  $newSlot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-12 10:00:00'));
  $appointment = $this->appointment($patient, $doctor, $service, $oldSlot);

  $response = $this->actingAs($patientUser)
    ->from('/patient/appointments')
    ->post("/appointments/{$appointment->id}/reschedule", [
      'slot_start' => $newSlot->key,
    ]);

  $response->assertRedirect('/patient/appointments');
  $response->assertSessionHasErrors('start_at');
  $this->assertSame($oldSlot->key, $appointment->fresh()->start_at->format('Y-m-d\TH:i'));
  CarbonImmutable::setTestNow();
}
```

- [x] **Step 3: Verify RED**

Run:

```bash
php artisan test tests/Feature/AppointmentWorkflowTest.php --filter='within_24_hours'
```

Expected: both tests fail because the appointment can still be cancelled or rescheduled.

- [x] **Step 4: Implement cutoff in service**

In `AppointmentService::validateFutureConfirmed()`, after the past-appointment check, add:

```php
if ($appointment->start_at->lte(now()->addDay())) {
  $messages = [
    'cancelled' => 'Gli appuntamenti non possono essere annullati nelle 24 ore precedenti.',
    'rescheduled' => 'Gli appuntamenti non possono essere spostati nelle 24 ore precedenti.',
  ];

  throw ValidationException::withMessages([
    'start_at' => $messages[$action] ?? 'Gli appuntamenti non possono essere modificati nelle 24 ore precedenti.',
  ]);
}
```

- [x] **Step 5: Verify GREEN**

Run:

```bash
php artisan test tests/Feature/AppointmentWorkflowTest.php --filter='within_24_hours'
```

Expected: PASS.

### Task 2: Patient appointment actions

**Files:**
- Modify: `tests/Feature/AppointmentWorkflowTest.php`
- Modify: `resources/views/patient/partials/appointment-card.blade.php`

- [x] **Step 1: Write failing UI test**

Add this test near `test_patient_appointments_page_renders_compact_action_menu_and_cancel_modal`:

```php
public function test_patient_appointments_page_hides_change_actions_within_24_hours(): void
{
  CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-10 09:00:00'));
  [$patientUser, $patient, $doctor, $service] = $this->bookingContext(createSlot: false);
  $slot = $this->slotAt($doctor, CarbonImmutable::parse('2026-05-11 08:59:00'));
  $this->appointment($patient, $doctor, $service, $slot);

  $response = $this->actingAs($patientUser)->get('/patient/appointments');

  $response->assertOk();
  $response->assertDontSee('Sposta appuntamento');
  $response->assertDontSee('Annulla appuntamento');
  $response->assertSee('Modifiche non disponibili nelle 24 ore precedenti');
  CarbonImmutable::setTestNow();
}
```

- [x] **Step 2: Verify RED**

Run:

```bash
php artisan test tests/Feature/AppointmentWorkflowTest.php --filter='hides_change_actions'
```

Expected: FAIL because the action menu still appears.

- [x] **Step 3: Implement UI cutoff**

At the top of `appointment-card.blade.php`, compute:

```php
$changeLocked = $appointment->start_at->lte(now()->addDay());
```

Then show the action menu only when:

```php
@if ($manageable && ! $changeLocked)
```

Below the header actions, show:

```php
@if ($manageable && $changeLocked)
  <p class="appointment-card__notice">Modifiche non disponibili nelle 24 ore precedenti.</p>
@endif
```

- [x] **Step 4: Verify GREEN**

Run:

```bash
php artisan test tests/Feature/AppointmentWorkflowTest.php --filter='hides_change_actions'
```

Expected: PASS.

### Task 3: Regression run

**Files:**
- Test only: `tests/Feature/AppointmentWorkflowTest.php`

- [x] **Step 1: Run appointment workflow tests**

Run:

```bash
php artisan test tests/Feature/AppointmentWorkflowTest.php
```

Expected: PASS.

- [x] **Step 2: Check final diff**

Run:

```bash
git diff -- app/Services/AppointmentService.php resources/views/patient/partials/appointment-card.blade.php tests/Feature/AppointmentWorkflowTest.php
```

Expected: diff only contains the 24h cutoff behavior, UI note, and tests.
