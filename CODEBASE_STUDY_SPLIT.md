# Codebase Study Split

This guide divides the Laravel appointment MVP between two people for study and presentation.
The split favors feature ownership where possible, with one deliberate JS-only balancing assignment noted below.

## Counting Rules

Source for PHP and Blade line counts: `.VSCodeCounter/2026-05-24_03-36-08/results.json`.

LOC means the `code` value reported by VSCodeCounter for existing PHP/Blade files. The split JavaScript files were created after that counter run, so their LOC uses current nonblank, non-comment source lines from `resources/js/`.

Excluded from study workload:

- `vendor/`
- `docs/`
- `node_modules/`
- `.idea/`
- generated/cache folders
- `tests/`
- `database/migrations/`
- all CSS, including `resources/css/app.css` and `resources/css/components/*`
- setup/config files such as `README.md`, `bootstrap/`, `config/`, package manifests, Docker files, Vite config, `public/index.php`, and seeders
- lockfiles such as `composer.lock` and `package-lock.json`
- `resources/data/comuni-italiani.json`

Study workload after filtering: 5,055 code lines.

## Workload Summary

| Person | Ownership | Estimated LOC |
| --- | --- | ---: |
| Person A | Auth, patient journey, appointment lifecycle, plus one balancing JS helper | 2,549 |
| Person B | Doctor agenda, scheduling, availability management, treatments | 2,506 |

The difference is 43 LOC. This is balanced enough while keeping the major backend/frontend use cases coherent.

## Person A: Auth, Patient Booking, Appointment Lifecycle

Presentation angle: how a patient enters the system and books or manages an appointment.

Person A total: 2,549 LOC.

### Own And Present

- Auth flow: login, register, logout, role redirects, unsupported role.
- Patient dashboard and profile management.
- Patient booking flow: service selection, week/day/slot selection, confirmation, booking creation.
- Patient appointment management: appointment list, cancellation, reschedule, 24-hour lock rules.
- Catalog and availability endpoints from the patient-facing perspective.
- Frontend behavior for patient booking, comune autocomplete, and the working-hours form helper assigned here only to balance JS workload.

### Code Areas

Controllers: 389 LOC

- `app/Http/Controllers/AuthController.php` - 116 LOC
- `app/Http/Controllers/AvailabilityController.php` - 32 LOC
- `app/Http/Controllers/BookingController.php` - 59 LOC
- `app/Http/Controllers/CatalogController.php` - 25 LOC
- `app/Http/Controllers/PatientAppointmentController.php` - 72 LOC
- `app/Http/Controllers/PatientDashboardController.php` - 69 LOC
- `app/Http/Middleware/EnsureRole.php` - 16 LOC

Services: 620 LOC

- `app/Services/AppointmentService.php` - 209 LOC
- `app/Services/AvailabilityService.php` - 141 LOC
- `app/Services/CodiceFiscaleService.php` - 119 LOC
- `app/Services/PatientBookingCalendarService.php` - 151 LOC

Models and support: 314 LOC

- `app/Models/Appointment.php` - 94 LOC
- `app/Models/MedicalService.php` - 23 LOC
- `app/Models/PatientProfile.php` - 35 LOC
- `app/Models/User.php` - 61 LOC
- `app/Support/ComuniItalianiCatalog.php` - 31 LOC
- `app/Support/PortalFormat.php` - 42 LOC
- `app/Support/ValidationRules.php` - 15 LOC
- `app/Support/VirtualAvailabilitySlot.php` - 13 LOC

Views: 849 LOC

- `resources/views/auth/login.blade.php` - 34 LOC
- `resources/views/auth/register.blade.php` - 92 LOC
- `resources/views/auth/unsupported.blade.php` - 13 LOC
- `resources/views/components/appointment-cancel-modal.blade.php` - 30 LOC
- `resources/views/components/status-badge.blade.php` - 23 LOC
- `resources/views/layouts/guest.blade.php` - 13 LOC
- `resources/views/layouts/portal.blade.php` - 98 LOC
- `resources/views/patient/appointment-edit.blade.php` - 15 LOC
- `resources/views/patient/appointments.blade.php` - 36 LOC
- `resources/views/patient/booking.blade.php` - 10 LOC
- `resources/views/patient/dashboard.blade.php` - 46 LOC
- `resources/views/patient/partials/appointment-card.blade.php` - 85 LOC
- `resources/views/patient/partials/booking-week-partial.blade.php` - 165 LOC
- `resources/views/patient/partials/booking-wizard.blade.php` - 90 LOC
- `resources/views/patient/profile.blade.php` - 99 LOC

JavaScript: 377 LOC

- `resources/js/components/comune-combobox.js` - 109 LOC
- `resources/js/components/patient-booking.js` - 77 LOC
- `resources/js/components/working-hours.js` - 191 LOC

Note: `working-hours.js` is assigned to Person A only to balance the split after JS extraction. Person B still owns the doctor scheduling behavior and views that use this helper.

### Suggested Code Trace

Trace these flows in the presentation:

- `GET /login` -> `AuthController@showLogin` -> `resources/views/auth/login.blade.php`
- `POST /register` -> `AuthController@register` -> `User` + `PatientProfile` -> patient dashboard
- `GET /patient/book` -> `BookingController@show` -> `PatientBookingCalendarService` -> `AvailabilityService` -> `resources/views/patient/booking.blade.php`
- `GET /patient/book/week` -> `BookingController@week` -> booking week partial for AJAX refresh
- `POST /appointments` -> `BookingController@store` -> `AppointmentService@book`
- `POST /appointments/{appointment}/cancel` -> `PatientAppointmentController@cancel` -> `AppointmentService@cancelByPatient`
- `POST /appointments/{appointment}/reschedule` -> `PatientAppointmentController@reschedule` -> `AppointmentService@reschedule`

## Person B: Doctor Agenda, Schedule, Treatments

Presentation angle: how the doctor defines availability and manages the live agenda.

Person B total: 2,506 LOC.

### Own And Present

- Doctor agenda page and timeline.
- Appointment status changes and doctor-side cancellation.
- Working hours template behavior from backend/service/view perspective.
- Closures, special openings, and blocked slots.
- Schedule conflict confirmation flow that cancels affected appointments.
- Doctor profile and treatment/service management.
- Frontend behavior for doctor agenda timeline, app JS entrypoint, and auto-show modals.

### Code Areas

Controllers: 425 LOC

- `app/Http/Controllers/DoctorDashboardController.php` - 229 LOC
- `app/Http/Controllers/DoctorProfileController.php` - 89 LOC
- `app/Http/Controllers/DoctorTreatmentController.php` - 76 LOC
- `app/Http/Controllers/Concerns/ConfirmsScheduleAppointmentCancellations.php` - 31 LOC

Services: 777 LOC

- `app/Services/DoctorAgendaViewService.php` - 206 LOC
- `app/Services/DoctorScheduleService.php` - 337 LOC
- `app/Services/ScheduleAppointmentImpactService.php` - 128 LOC
- `app/Services/ScheduleWindowService.php` - 106 LOC

Models and support: 212 LOC

- `app/Exceptions/ScheduleAppointmentConflictsException.php` - 22 LOC
- `app/Models/DoctorProfile.php` - 43 LOC
- `app/Models/ScheduleClosure.php` - 25 LOC
- `app/Models/SpecialOpening.php` - 24 LOC
- `app/Models/WorkingHour.php` - 29 LOC
- `app/Providers/AppServiceProvider.php` - 12 LOC
- `app/Support/ScheduleTime.php` - 57 LOC

Views: 1,006 LOC

- `resources/views/components/schedule-confirmation-modal.blade.php` - 57 LOC
- `resources/views/components/time-select.blade.php` - 32 LOC
- `resources/views/doctor/agenda.blade.php` - 464 LOC
- `resources/views/doctor/partials/appointment-info-modal.blade.php` - 101 LOC
- `resources/views/doctor/profile.blade.php` - 226 LOC
- `resources/views/doctor/treatments.blade.php` - 126 LOC

JavaScript: 86 LOC

- `resources/js/app.js` - 13 LOC
- `resources/js/components/agenda.js` - 68 LOC
- `resources/js/components/auto-modals.js` - 5 LOC

### Suggested Code Trace

Trace these flows in the presentation:

- `GET /doctor/agenda` -> `DoctorDashboardController@agenda` -> `DoctorAgendaViewService@build` -> `resources/views/doctor/agenda.blade.php`
- `POST /doctor/appointments/{appointment}/status` -> `DoctorDashboardController@updateStatus` -> `AppointmentService@updateByDoctor`
- `POST /doctor/appointments/{appointment}/cancel` -> `DoctorDashboardController@cancelAppointment` -> `AppointmentService@cancelByDoctor`
- `PATCH /doctor/profile/working-hours` -> `DoctorProfileController@updateWorkingHours` -> `DoctorScheduleService@replaceWeeklyTemplate`
- `POST /doctor/closures` -> `DoctorDashboardController@storeClosure` -> `DoctorScheduleService@createClosure`
- `POST /doctor/special-openings` -> `DoctorDashboardController@storeSpecialOpening` -> `DoctorScheduleService@createSpecialOpening`
- `POST /doctor/availability/block` -> `DoctorDashboardController@blockAvailability` -> closure/blocking behavior
- `POST /doctor/treatments` and related treatment routes -> `DoctorTreatmentController`

## Shared Sync Points

Both people should align briefly on these, without treating setup, tests, migrations, or CSS as study workload:

- `routes/web.php`: skim only as a map for the feature routes each person presents.
- Database model concepts: users, profiles, services, appointments, working hours, closures, and special openings.
- `AppointmentService` and `AvailabilityService`: Person A presents them, but Person B should understand them because doctor agenda and scheduling depend on the same appointment and slot rules.
- `resources/js/app.js`: Person B owns the small entrypoint, but both should know it wires together all JS components.
- Appointment status rules, 24-hour lock behavior, generated availability, and cancellation conflict confirmation.

## Final LOC Totals

| Bucket | LOC |
| --- | ---: |
| Person A controllers | 389 |
| Person A services | 620 |
| Person A models/support | 314 |
| Person A views | 849 |
| Person A JavaScript | 377 |
| Person A subtotal | 2,549 |
| Person B controllers | 425 |
| Person B services | 777 |
| Person B models/support | 212 |
| Person B views | 1,006 |
| Person B JavaScript | 86 |
| Person B subtotal | 2,506 |
| Grand total | 5,055 |

## Presentation Agenda

| Time | Topic | Lead |
| --- | --- | --- |
| 5 min | App overview and feature route map, using routes only as navigation aid | Person A |
| 12-15 min | Patient/auth/booking journey | Person A |
| 12-15 min | Doctor agenda/scheduling journey | Person B |
| 5 min | Shared business rules: statuses, 24-hour lock, generated availability, cancellation conflicts | Both |

## Study Checklist

Person A:

- Explain auth and patient-facing entry points.
- Draw the patient booking flow from route to controller to service to model to Blade view.
- Explain how available slots are generated and why duplicate bookings are rejected.
- Explain patient cancellation/reschedule rules and the 24-hour lock.
- Explain the patient-facing JS components and the assigned working-hours helper.

Person B:

- Draw the doctor agenda flow from route to controller to agenda view service to Blade timeline.
- Explain how working hours, closures, and special openings become available or blocked slots.
- Explain schedule conflict confirmation and affected appointment cancellation.
- Explain doctor treatment/profile management and role access protection.
- Explain the agenda JS, modal bootstrapping, and app JS entrypoint.

## Boundaries

- Do not study dependency code in `vendor/`.
- Do not study `docs/` for this presentation.
- Do not study CSS files for this workload split.
- Do not study `tests/`, setup/config files, seeders, or database migrations for this workload split.
- Treat `resources/data/comuni-italiani.json` as static data behind the comune lookup, not as code to present line by line.
- Treat lockfiles as dependency snapshots, not presentation material.