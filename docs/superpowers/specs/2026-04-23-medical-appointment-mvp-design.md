# Medical Appointment System MVP Design

Date: 2026-04-23

## Goal

Build a web application for medical appointment booking using Django, PostgreSQL, React, and Bootstrap. The first release is a focused appointment-booking MVP with patient accounts, doctor accounts, appointment management, a staff dashboard, and Django admin setup tools. Later releases can add broader patient-portal features such as documents, payments, reports, queue management, and richer communications.

The product is inspired by the public structure of the referenced appointment portal, but it will use an original brand, layout, content, and UI implementation.

Reference pages reviewed:

- https://mypoli.poliambulanza.it/prenoting/index
- https://mypoli.poliambulanza.it/prenoting/home
- https://mypoli.poliambulanza.it/prenoting/nuova-registrazione
- https://mypoli.poliambulanza.it/prenoting/faq
- https://mypoli.poliambulanza.it/faq-mypoli

## Product Scope

The MVP includes:

- Patient registration and login.
- Patient dashboard.
- Booking by service.
- Booking by doctor.
- Appointment search and filtering.
- Appointment confirmation.
- Patient appointment management: view, reschedule, cancel.
- Doctor login and dashboard.
- Doctor schedule and appointment detail views.
- Staff login and operational appointment dashboard.
- Django admin for managing setup data.

The MVP does not include:

- Online payments.
- Lab or radiology reports.
- Clinical document archive.
- Insurance claim workflows.
- Televisit video calls.
- Queue-skipping or reception ticketing.
- Email/SMS delivery beyond backend-ready hooks.

## Architecture

The application will use a separated backend and frontend.

Backend:

- Django.
- Django REST Framework.
- PostgreSQL.
- Django auth with role-based permissions.
- Django admin for setup and master data.

Frontend:

- React.
- Bootstrap.
- A modern portal layout with a desktop sidebar and mobile top navigation.
- Separate experiences for patients, doctors, and staff.

Core backend modules:

- `accounts`: users, registration, login, roles, profiles.
- `clinics`: clinic locations.
- `providers`: doctor profiles and specialties.
- `services`: medical services, exams, visits, and categories.
- `scheduling`: doctor availability and appointment slots.
- `appointments`: booking, cancellation, rescheduling, and status history.
- `doctor_dashboard`: doctor-facing schedule and appointment actions.
- `staff_dashboard`: staff-facing operational appointment views.

## Roles

`Patient`:

- Registers and logs in.
- Maintains personal profile data.
- Searches by service or doctor.
- Books available appointment slots.
- Views, reschedules, and cancels own appointments.

`Doctor`:

- Logs in with a linked doctor profile.
- Views personal schedule.
- Opens assigned appointment details.
- Updates appointment outcomes and workflow statuses.

`Staff`:

- Logs in to an operational dashboard.
- Views appointments across doctors, services, clinics, dates, and statuses.
- Updates appointment workflow statuses.
- Opens appointment details for operational support.

`Admin`:

- Uses Django admin.
- Manages users, doctors, specialties, services, clinic locations, availability slots, and appointment records.

## Screens

Public and authentication screens:

- Landing page with clear entry points for login, registration, and booking.
- Login page.
- Patient registration page.

Patient screens:

- Patient dashboard with upcoming appointments and booking actions.
- Search page with tabs for `Book by service` and `Book by doctor`.
- Search results with filters for clinic, specialty, doctor, service, date, and availability.
- Slot selection page using date cards and time-slot buttons.
- Booking confirmation page with patient details and appointment summary.
- My appointments page with view, reschedule, and cancel actions.
- Appointment detail page.

Doctor screens:

- Doctor dashboard with today's appointments.
- Doctor schedule list/calendar.
- Appointment detail with patient, service, clinic, time, notes, and status controls.

Staff screens:

- Staff dashboard with daily appointment counts and filters.
- Appointment list filtered by date, clinic, doctor, service, and status.
- Appointment detail with status update controls.

Admin screens:

- Django admin for all setup and master data.

## Booking Flow

The booking flow supports both service-first and doctor-first paths.

Service-first:

1. Patient selects `Book by service`.
2. Patient searches or filters services.
3. Patient selects a service.
4. The system shows matching doctors and available slots.
5. Patient selects a slot.
6. Patient confirms booking details.
7. The backend creates the appointment transactionally.
8. Dashboards update for patient, doctor, and staff.

Doctor-first:

1. Patient selects `Book by doctor`.
2. Patient searches or filters doctors.
3. Patient selects a doctor.
4. The system shows services offered by that doctor and available slots.
5. Patient selects service and slot.
6. Patient confirms booking details.
7. The backend creates the appointment transactionally.
8. Dashboards update for patient, doctor, and staff.

Appointment creation must prevent double booking when two patients attempt to reserve the same slot at the same time.

## Data Model

Main models:

- `User`: Django auth user.
- `PatientProfile`: user, date of birth, gender, phone, address, identity/fiscal code if enabled.
- `DoctorProfile`: user, display name, specialty, bio, license number, active status.
- `ClinicLocation`: name, address, phone, active status.
- `Specialty`: name and description.
- `MedicalService`: name, category, specialty, duration, price, active status.
- `DoctorService`: relation between doctors and services they can perform.
- `AvailabilitySlot`: doctor, clinic, start time, end time, compatible services, booked/blocked state.
- `Appointment`: patient, doctor, service, clinic, start time, end time, status, notes, cancellation reason.

Core appointment statuses:

- `confirmed`
- `checked_in`
- `completed`
- `cancelled`
- `no_show`

## API Design

Core API groups:

- `/api/auth/`: register, login, logout, current user.
- `/api/patient/`: profile and patient dashboard.
- `/api/doctors/`: doctor list, detail, search, filters.
- `/api/services/`: service list, detail, search, filters.
- `/api/availability/`: available slots by service, doctor, clinic, and date.
- `/api/appointments/`: create, list mine, detail, reschedule, cancel.
- `/api/doctor/`: doctor's own schedule and appointment actions.
- `/api/staff/`: staff dashboard, appointment list, status updates.

API errors should use a consistent JSON shape with a general message and optional field errors.

## UI Direction

The approved UI direction is `Modern Portal`.

Key UI traits:

- Desktop sidebar navigation for portal areas.
- Mobile top navigation.
- Clean dashboard cards for counts and next actions.
- Dense appointment tables for staff and doctor views.
- Tabs for service-first and doctor-first booking.
- Date cards and time-slot buttons for availability.
- Status badges for appointment workflow.
- Bootstrap components customized with restrained colors, strong spacing, and clear hierarchy.

The first visual target includes:

- Patient dashboard.
- Booking search and results.
- Upcoming appointments.
- Doctor dashboard.
- Staff dashboard.

## Error Handling

Authentication:

- Invalid credentials show a clear login error.
- Registration validation shows field-level messages.
- Unauthorized access redirects to the correct dashboard when possible or returns a 403 page.

Booking:

- No available slots keeps the user's filters and suggests alternate dates.
- A slot taken during confirmation returns a `slot no longer available` message and refreshes availability.
- Reschedule and cancellation actions require confirmation modals.
- Backend booking uses a transaction and row-level locking or an equivalent integrity constraint to prevent double booking.

Dashboards:

- Empty states show clear next actions.
- Failed API requests show retryable alerts.
- Loading states avoid layout jumps.

## Testing

Backend tests:

- Model tests for appointments, slots, doctor-service relationships, and status history.
- Permission tests for patient, doctor, staff, and admin access.
- API tests for registration, login, search, availability, booking, rescheduling, cancellation, doctor dashboard, and staff dashboard.
- Concurrency test or transaction-level test for double-booking prevention.

Frontend tests:

- Key rendering tests for dashboards and booking states where practical.
- Form validation tests for registration and booking confirmation.
- Manual browser checks for patient, doctor, and staff flows.

Development seed data:

- Clinic locations.
- Specialties.
- Medical services.
- Doctors.
- Doctor-service mappings.
- Availability slots.
- Sample patient, doctor, staff, and admin users.

## Future Phases

After the MVP:

- Notifications by email and SMS.
- Payments.
- Medical reports and document archive.
- Insurance workflows.
- Patient family/dependent booking.
- Televisit support.
- Queue/check-in workflows.
- Advanced analytics for staff and administrators.
