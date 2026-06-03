# Medical Service Doctor Ownership Design

## Goal

Treatments are created by doctors, so `medical_services` must own the `doctor_profile_id` relationship. Appointments should derive their doctor through the linked medical service instead of storing a redundant `appointments.doctor_profile_id`.

## Architecture

`medical_services.doctor_profile_id` becomes the source of truth for treatment ownership. `Appointment` keeps `service_id` and exposes doctor access through the related service. Doctor-facing appointment queries filter appointments by the service owner.

## Data Migration

Add `doctor_profile_id` to `medical_services`, backfill from appointments when possible, and fall back to the first doctor profile for services without appointments. After the backfill, make service ownership required and remove `doctor_profile_id` from `appointments`.

## Backend Behavior

Doctor treatment CRUD is scoped to the authenticated doctor. Booking uses the selected service's doctor for availability validation. Rescheduling, agenda views, appointment listings, cancellation impact checks, and authorization all derive the appointment doctor from `appointment.service.doctor`.

## Testing

Feature tests should assert the new schema shape, service ownership, booking derivation, and doctor-scoped appointment visibility.
