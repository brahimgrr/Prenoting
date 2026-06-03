# Medical Service Doctor Ownership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move doctor ownership from appointments to medical services.

**Architecture:** Add `medical_services.doctor_profile_id` and update models so appointments reach doctors through their service. Replace appointment doctor-column queries with `whereHas('service', ...)` filters and migrate existing data before dropping the redundant appointment column.

**Tech Stack:** Laravel, Eloquent, PHPUnit feature tests, database migrations.

---

### Task 1: Failing Schema And Booking Tests

**Files:**
- Modify: `tests/Feature/RoleDashboardTest.php`
- Modify: `tests/Feature/AppointmentWorkflowTest.php`

- [ ] Add assertions that `medical_services` has `doctor_profile_id` and `appointments` does not.
- [ ] Add or update a booking assertion that the created appointment derives the doctor from `$appointment->service->doctor_profile_id`.
- [ ] Run the targeted tests and verify they fail before implementation.

### Task 2: Schema And Model Ownership

**Files:**
- Create: `database/migrations/2026_06_04_000002_move_doctor_ownership_to_medical_services.php`
- Modify: `app/Models/MedicalService.php`
- Modify: `app/Models/Appointment.php`
- Modify: `app/Models/DoctorProfile.php`

- [ ] Add the migration with backfill from existing appointments, then first doctor fallback.
- [ ] Add `MedicalService::doctor()` and `DoctorProfile::medicalServices()`.
- [ ] Replace `Appointment::doctor()` with service-derived access and update portal eager loading.
- [ ] Run the targeted tests and verify schema/model failures move forward.

### Task 3: Backend Query Updates

**Files:**
- Modify: `app/Services/AppointmentService.php`
- Modify: `app/Services/AvailabilityService.php`
- Modify: `app/Services/DoctorAgendaViewService.php`
- Modify: `app/Services/ScheduleAppointmentImpactService.php`
- Modify: `app/Http/Controllers/DoctorAppointmentController.php`
- Modify: `app/Http/Controllers/DoctorDashboardController.php`
- Modify: `app/Http/Controllers/DoctorTreatmentController.php`
- Modify: `app/Services/PatientBookingWizardViewData.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] Derive booking and rescheduling doctor from the service.
- [ ] Replace direct appointment doctor filters with service-owner filters.
- [ ] Scope treatment CRUD and booking catalogs to service ownership.
- [ ] Seed service ownership for the seeded doctor.

### Task 4: Test Fixture Updates And Verification

**Files:**
- Modify tests that create appointments or medical services without service ownership.

- [ ] Update factories/inline fixtures to put `doctor_profile_id` on `MedicalService`.
- [ ] Remove appointment fixture writes to the dropped doctor column.
- [ ] Run targeted feature tests, then the full PHPUnit suite.
