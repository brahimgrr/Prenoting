# Landing Doctor Data Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an "Il nostro medico" landing-page section that displays real active doctor profile data and omits missing fields.

**Architecture:** `LandingController` queries one active doctor profile with its user relation, the landing Blade view conditionally renders the section and each available data row, and `landing.css` styles the section with existing design tokens. PHPUnit feature tests verify real data, missing-value omission, and the no-doctor case.

**Tech Stack:** Laravel, Blade, PHPUnit feature tests, Bootstrap utility classes, CSS custom properties.

---

## File Map

- Modify: `tests/Feature/RootRouteTest.php` to align the guest root test with the current public landing page and add doctor section tests.
- Modify: `app/Http/Controllers/LandingController.php` to load the active doctor profile.
- Modify: `resources/views/landing.blade.php` to add the "Il nostro medico" section.
- Modify: `resources/css/landing.css` to style the new section.

## Task 1: Add Failing Feature Tests

- [ ] Add imports for `App\Models\DoctorProfile` and `App\Models\MedicalService`.
- [ ] Update the guest root assertion to expect the public landing page.
- [ ] Add a test that creates an active doctor with email, phone, clinic address, and license number, then asserts the landing page shows "Il nostro medico" and those real values.
- [ ] Add a test that creates a doctor with empty optional values and asserts the landing page does not show placeholder text or empty labels.
- [ ] Add a test that asserts the section is absent when no active doctor profile exists.
- [ ] Run `./vendor/bin/phpunit tests/Feature/RootRouteTest.php` and verify the new doctor tests fail before implementation.

## Task 2: Load Active Doctor Data

- [ ] Update `LandingController` to import `DoctorProfile`.
- [ ] Query the first profile whose related user has `is_active = true`, eager-load the user, and pass it as `doctor` to the landing view.
- [ ] Keep the existing service query unchanged.

## Task 3: Render The Section

- [ ] Add a conditional `@if ($doctor)` block after the treatments section.
- [ ] Render title "Il nostro medico" and the doctor name.
- [ ] Render email from `$doctor->user->email` only when present.
- [ ] Render phone, clinic address, and license number only when present.
- [ ] Avoid any fallback placeholder for missing values.

## Task 4: Style And Verify

- [ ] Add `.landing-doctor` styles in `resources/css/landing.css`.
- [ ] Run `./vendor/bin/phpunit tests/Feature/RootRouteTest.php` until the targeted feature tests pass.
- [ ] Run the broader relevant test suite with `./vendor/bin/phpunit`.
