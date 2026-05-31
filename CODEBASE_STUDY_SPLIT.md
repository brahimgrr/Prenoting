# Codebase Study Split

This guide divides the current Laravel appointment MVP between two people for study and presentation.
It reflects the current `dynamic-availability-model` working tree and uses the fresh VS Code Counter snapshot from `2026-05-31_20-27-01`.

## Recent Project Changes Reflected Here

- Booking and rescheduling now support AJAX partial refreshes through `BookingController`, `PatientAppointmentController`, `resources/views/patient/partials/booking-page.blade.php`, `booking-week-partial.blade.php`, and `booking-interactions.blade.php`.
- Booking view data was centralized in `PatientBookingWizardViewData`, so booking and reschedule pages share the same data-building path.
- Doctor agenda/scheduling logic is split between controllers and services: `DoctorAgendaViewService`, `DoctorScheduleService`, `ScheduleWindowService`, and `ScheduleAppointmentImpactService`.
- CSS was split into feature files under `resources/css/`; layout/responsive positioning is mostly handled in Blade through Bootstrap utilities.
- JavaScript components under `resources/js/components/` are no longer part of the current tree. `resources/js/app.js` only imports Bootstrap; page-specific scripts now live in Blade views.
- Use-case documentation was updated separately. It is not counted in the study workload because this split focuses on application code.

## VS Code Counter Statistics

Counter snapshot used:

```bash
.VSCodeCounter/2026-05-31_20-27-01/results.md
.VSCodeCounter/2026-05-31_20-27-01/details.md
```

The raw root count is **627,584 code lines** and **1,015,368 total lines**, but that includes `vendor/`, IDE metadata, generated/cache files, dependency manifests, and framework files. For presentation, use the focused project counts below.

| Area | Lines |
| --- | ---: |
| `app/` | 2,760 |
| `resources/views/` | 2,168 |
| `resources/css/` | 809 |
| `resources/js/` | 2 |
| `routes/` | 71 |
| `database/` | 817 |
| `tests/` | 3,030 |
| Focused project total | 9,657 |

Application bucket detail:

| Bucket | Lines |
| --- | ---: |
| Controllers | 754 |
| Middleware | 16 |
| Services | 1,455 |
| Models | 333 |
| Support | 168 |
| Providers | 12 |
| Exceptions | 22 |

CSS module detail:

| CSS file | Lines |
| --- | ---: |
| `resources/css/app.css` | 12 |
| `resources/css/base.css` | 81 |
| `resources/css/auth.css` | 74 |
| `resources/css/portal-layout.css` | 73 |
| `resources/css/shared-ui.css` | 44 |
| `resources/css/patient-dashboard.css` | 30 |
| `resources/css/appointments.css` | 59 |
| `resources/css/booking.css` | 95 |
| `resources/css/profile.css` | 15 |
| `resources/css/doctor-agenda.css` | 191 |
| `resources/css/treatments.css` | 33 |
| `resources/css/modals.css` | 102 |
| CSS total | 809 |

Largest files to be aware of:

| File | Lines | Why it matters |
| --- | ---: | --- |
| `tests/Feature/DoctorSchedulingUxTest.php` | 1,103 | Broad doctor scheduling regression coverage |
| `tests/Feature/AuthTest.php` | 475 | Auth, registration, profile validation coverage |
| `resources/views/doctor/agenda.blade.php` | 463 | Main doctor agenda UI and inline agenda script |
| `tests/Feature/AppointmentWorkflowTest.php` | 424 | Patient appointment lifecycle coverage |
| `tests/Feature/RoleDashboardTest.php` | 407 | Role redirects and portal access coverage |
| `app/Services/DoctorScheduleService.php` | 367 | Core doctor availability mutation rules |
| `resources/views/doctor/profile.blade.php` | 289 | Doctor profile and working-hours UI |

## Counting Rules For Study Workload

Source for all line counts: `.VSCodeCounter/2026-05-31_20-27-01/details.md`.

LOC means the numeric `code` count reported by VS Code Counter.

Excluded from study workload:

- `vendor/`
- `node_modules/`
- `docs/`
- generated/cache/build folders, including `storage/framework/views/` and `public/build/`
- `tests/`
- `database/migrations/` and `database/seeders/`
- all CSS files
- setup/config files such as `README.md`, `bootstrap/`, `config/`, package manifests, Docker files, Vite config, `public/index.php`, and lockfiles
- static data such as `resources/data/comuni-italiani.json`
- base framework placeholder `app/Http/Controllers/Controller.php`

Study workload after filtering: **4,925 code lines**.

## Workload Summary

| Person | Ownership | Estimated LOC |
| --- | --- | ---: |
| Person A | Auth, patient journey, booking/reschedule AJAX flow, appointment lifecycle, shared UI shell | 2,467 |
| Person B | Doctor agenda, scheduling, availability management, treatments, Bootstrap JS entrypoint | 2,458 |

The difference is **9 LOC**, so the split is balanced while keeping the main product areas coherent.

## Person A: Auth, Patient Booking, Appointment Lifecycle

Presentation angle: how a patient enters the system, books an appointment, and manages appointment changes.

Person A total: **2,467 LOC**.

### Own And Present

- Auth flow: login, register, logout, role redirects, unsupported role.
- Patient dashboard and patient profile management.
- Comune autocomplete and codice fiscale generation during registration.
- Patient booking flow: service selection, week/day/slot selection, AJAX refresh, confirmation, booking creation.
- Patient reschedule flow using the same booking wizard and AJAX week partial.
- Patient appointment management: appointment list, cancellation, reschedule, 24-hour lock rules.
- Catalog and availability endpoints from the patient-facing perspective.
- Shared portal shell and patient-facing UI components.

### Code Areas

Controllers and middleware: **392 LOC**

- `app/Http/Controllers/AuthController.php` - 135 LOC
- `app/Http/Controllers/AvailabilityController.php` - 32 LOC
- `app/Http/Controllers/BookingController.php` - 44 LOC
- `app/Http/Controllers/CatalogController.php` - 25 LOC
- `app/Http/Controllers/PatientAppointmentController.php` - 69 LOC
- `app/Http/Controllers/PatientDashboardController.php` - 71 LOC
- `app/Http/Middleware/EnsureRole.php` - 16 LOC

Services: **663 LOC**

- `app/Services/AppointmentService.php` - 209 LOC
- `app/Services/AvailabilityService.php` - 141 LOC
- `app/Services/CodiceFiscaleService.php` - 119 LOC
- `app/Services/PatientBookingCalendarService.php` - 148 LOC
- `app/Services/PatientBookingWizardViewData.php` - 46 LOC

Models and support: **323 LOC**

- `app/Models/Appointment.php` - 93 LOC
- `app/Models/MedicalService.php` - 23 LOC
- `app/Models/PatientProfile.php` - 35 LOC
- `app/Models/User.php` - 61 LOC
- `app/Support/ComuniItalianiCatalog.php` - 31 LOC
- `app/Support/PortalFormat.php` - 42 LOC
- `app/Support/ValidationRules.php` - 25 LOC
- `app/Support/VirtualAvailabilitySlot.php` - 13 LOC

Views and small UI components: **1,089 LOC**

- `resources/views/auth/login.blade.php` - 34 LOC
- `resources/views/auth/register.blade.php` - 202 LOC
- `resources/views/auth/unsupported.blade.php` - 13 LOC
- `resources/views/components/appointment-cancel-modal.blade.php` - 30 LOC
- `resources/views/components/status-badge.blade.php` - 23 LOC
- `resources/views/layouts/guest.blade.php` - 13 LOC
- `resources/views/layouts/portal.blade.php` - 101 LOC
- `resources/views/patient/appointment-edit.blade.php` - 8 LOC
- `resources/views/patient/appointments.blade.php` - 36 LOC
- `resources/views/patient/booking.blade.php` - 7 LOC
- `resources/views/patient/dashboard.blade.php` - 54 LOC
- `resources/views/patient/partials/appointment-card.blade.php` - 81 LOC
- `resources/views/patient/partials/booking-interactions.blade.php` - 90 LOC
- `resources/views/patient/partials/booking-page.blade.php` - 15 LOC
- `resources/views/patient/partials/booking-week-partial.blade.php` - 164 LOC
- `resources/views/patient/partials/booking-wizard.blade.php` - 107 LOC
- `resources/views/patient/profile.blade.php` - 111 LOC

### Suggested Code Trace

Trace these flows in the presentation:

- `GET /login` -> `AuthController@showLogin` -> `resources/views/auth/login.blade.php`
- `POST /register` -> `AuthController@register` -> `User` + `PatientProfile` -> patient dashboard
- `GET /patient/book` -> `BookingController@show` -> `PatientBookingWizardViewData@booking` -> `PatientBookingCalendarService` -> booking page partials
- AJAX `GET /patient/book` -> same controller -> `resources/views/patient/partials/booking-page.blade.php`
- `GET /patient/book/week` -> `BookingController@week` -> `resources/views/patient/partials/booking-week-partial.blade.php`
- `POST /appointments` -> `BookingController@store` -> `AppointmentService@book`
- `GET /appointments/{appointment}/edit` -> `PatientAppointmentController@edit` -> `PatientBookingWizardViewData@reschedule`
- `GET /appointments/{appointment}/edit/week` -> `PatientAppointmentController@editWeek` -> booking week partial for reschedule
- `POST /appointments/{appointment}/cancel` -> `PatientAppointmentController@cancel` -> `AppointmentService@cancelByPatient`
- `POST /appointments/{appointment}/reschedule` -> `PatientAppointmentController@reschedule` -> `AppointmentService@reschedule`

## Person B: Doctor Agenda, Schedule, Treatments

Presentation angle: how the doctor defines availability and manages the live agenda.

Person B total: **2,458 LOC**.

### Own And Present

- Doctor agenda page and timeline.
- Appointment status changes and doctor-side cancellation.
- Working hours template behavior from backend/service/view perspective.
- Closures, special openings, and blocked slots.
- Schedule conflict confirmation flow that cancels affected appointments.
- Doctor profile and treatment/service management.
- Agenda scrolling/current-time inline script.
- `resources/js/app.js` Bootstrap entrypoint.

### Code Areas

Controllers and concerns: **373 LOC**

- `app/Http/Controllers/DoctorDashboardController.php` - 173 LOC
- `app/Http/Controllers/DoctorProfileController.php` - 93 LOC
- `app/Http/Controllers/DoctorTreatmentController.php` - 76 LOC
- `app/Http/Controllers/Concerns/ConfirmsScheduleAppointmentCancellations.php` - 31 LOC

Services: **792 LOC**

- `app/Services/DoctorAgendaViewService.php` - 203 LOC
- `app/Services/DoctorScheduleService.php` - 367 LOC
- `app/Services/ScheduleAppointmentImpactService.php` - 116 LOC
- `app/Services/ScheduleWindowService.php` - 106 LOC

Models and support: **212 LOC**

- `app/Exceptions/ScheduleAppointmentConflictsException.php` - 22 LOC
- `app/Models/DoctorProfile.php` - 43 LOC
- `app/Models/ScheduleClosure.php` - 25 LOC
- `app/Models/SpecialOpening.php` - 24 LOC
- `app/Models/WorkingHour.php` - 29 LOC
- `app/Providers/AppServiceProvider.php` - 12 LOC
- `app/Support/ScheduleTime.php` - 57 LOC

Views: **1,079 LOC**

- `resources/views/components/schedule-confirmation-modal.blade.php` - 66 LOC
- `resources/views/components/time-select.blade.php` - 32 LOC
- `resources/views/doctor/agenda.blade.php` - 463 LOC
- `resources/views/doctor/partials/appointment-info-modal.blade.php` - 101 LOC
- `resources/views/doctor/profile.blade.php` - 289 LOC
- `resources/views/doctor/treatments.blade.php` - 128 LOC

JavaScript: **2 LOC**

- `resources/js/app.js` - 2 LOC

### Suggested Code Trace

Trace these flows in the presentation:

- `GET /doctor/agenda` -> `DoctorDashboardController@agenda` -> `DoctorAgendaViewService@build` -> `resources/views/doctor/agenda.blade.php`
- `POST /doctor/appointments/{appointment}/status` -> `DoctorDashboardController@updateStatus` -> `AppointmentService@updateByDoctor`
- `POST /doctor/appointments/{appointment}/cancel` -> `DoctorDashboardController@cancelAppointment` -> `AppointmentService@cancelByDoctor`
- `PATCH /doctor/profile/working-hours` -> `DoctorProfileController@updateWorkingHours` -> `DoctorScheduleService@replaceWeeklyTemplate`
- `POST /doctor/closures` -> `DoctorDashboardController@storeClosure` -> `DoctorScheduleService@createClosure`
- `POST /doctor/special-openings` -> `DoctorDashboardController@storeSpecialOpening` -> `DoctorScheduleService@createSpecialOpening`
- `POST /doctor/availability/block` -> `DoctorDashboardController@blockAvailability` -> closure/blocking behavior
- `DELETE /doctor/closures/{closure}` and `DELETE /doctor/special-openings/{specialOpening}` -> conflict confirmation where needed
- `POST /doctor/treatments` and related treatment routes -> `DoctorTreatmentController`

## Shared Sync Points

Both people should align briefly on these, without treating setup, tests, migrations, or CSS as study workload:

- `routes/web.php`: skim only as the map for the feature routes each person presents.
- Database model concepts: users, profiles, services, appointments, working hours, closures, and special openings.
- `AppointmentService` and `AvailabilityService`: Person A presents them, but Person B should understand them because doctor agenda and scheduling depend on the same appointment and slot rules.
- `ScheduleWindowService`: Person B presents it, but Person A should understand it because patient availability is generated from the same schedule windows.
- `resources/js/app.js`: it only imports Bootstrap and exposes it on `window.bootstrap`; real page behavior is mostly inline in Blade views.
- Appointment status rules, 24-hour lock behavior, generated availability, and cancellation conflict confirmation.
- CSS split: explain that Bootstrap handles grid/responsive layout while CSS files mostly contain visual styling and component-specific refinements.

## Final LOC Totals

| Bucket | LOC |
| --- | ---: |
| Person A controllers/middleware | 392 |
| Person A services | 663 |
| Person A models/support | 323 |
| Person A views/UI components | 1,089 |
| Person A subtotal | 2,467 |
| Person B controllers/concerns | 373 |
| Person B services | 792 |
| Person B models/support | 212 |
| Person B views | 1,079 |
| Person B JavaScript | 2 |
| Person B subtotal | 2,458 |
| Grand total | 4,925 |

## Presentation Agenda

| Time | Topic | Lead |
| --- | --- | --- |
| 5 min | App overview and route map, using `routes/web.php` only as navigation aid | Person A |
| 12-15 min | Auth, registration, patient dashboard, booking, AJAX week refresh, appointment lifecycle | Person A |
| 12-15 min | Doctor agenda, working hours, closures/openings, conflict confirmation, treatments | Person B |
| 5 min | Shared business rules: statuses, 24-hour lock, generated availability, Bootstrap/CSS split | Both |

## Study Checklist

Person A:

- Explain auth and patient-facing entry points.
- Draw the patient booking flow from route to controller to view-data service to calendar service to Blade partials.
- Explain how available slots are generated and why duplicate bookings are rejected.
- Explain AJAX refresh: `booking-interactions.blade.php` calls patient booking URLs and replaces page/week partials.
- Explain patient cancellation/reschedule rules and the 24-hour lock.
- Explain why `resources/js/app.js` is small: most page-specific behavior is inline in the Blade view that owns the UI.

Person B:

- Draw the doctor agenda flow from route to controller to agenda view service to Blade timeline.
- Explain how working hours, closures, special openings, and blocks become available or blocked slots.
- Explain schedule conflict confirmation and affected appointment cancellation.
- Explain doctor treatment/profile management and role access protection.
- Explain the agenda inline script for scroll positioning and current-time marker.
- Explain the CSS responsibility after the split: Bootstrap for layout, CSS for visual style.

## Boundaries

- Do not study dependency code in `vendor/` or `node_modules/`.
- Do not study `docs/` for this split, except to understand presentation context.
- Do not study CSS line by line for workload purposes; use the CSS stats only to explain that styling was modularized.
- Do not study `tests/`, setup/config files, seeders, or database migrations line by line for the workload split.
- Treat `resources/data/comuni-italiani.json` as static data behind the comune lookup, not as code to present line by line.
- Treat lockfiles as dependency snapshots, not presentation material.
