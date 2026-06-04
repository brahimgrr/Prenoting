# Landing Doctor Data Section Design

**Date:** 2026-06-04

## Goal

Add an "Il nostro medico" section to the public landing page so visitors can read the real doctor data stored in the application.

## Scope

The section appears only when an active doctor profile exists. It renders real stored fields only: display name, email, phone, clinic address, and license number. Missing values are omitted completely; the section does not show placeholder text such as "Non indicato".

The application currently behaves as a single-doctor studio, so the landing page shows the first active doctor profile ordered by profile id. Multi-doctor listing, doctor photo upload, specialty copy, and hardcoded studio data are out of scope.

## Architecture

`LandingController` loads the active doctor profile with its user relation and passes it to `resources/views/landing.blade.php` as `$doctor`. The Blade template inserts the section between the treatments section and the booking steps. The view checks each field before rendering it.

CSS lives in `resources/css/landing.css`, following the existing landing-page class naming and visual tokens.

## Testing

Feature tests cover:
- The landing page shows the section and real doctor data for an active doctor.
- Empty doctor fields are not rendered as placeholders.
- The section is hidden when no active doctor exists.
