# Appointment Info Modal — Design Spec

**Date:** 2026-05-02

## Goal

Add an "Informazioni" button on every booked appointment card in the doctor's agenda (dashboard) and availability views. Clicking it opens a Bootstrap modal showing patient details, service details, and booking notes.

## Scope

- `resources/views/doctor/dashboard.blade.php` — appointment items in agenda
- `resources/views/doctor/availability.blade.php` — booked slots in availability panel
- No controller changes — data already eager-loaded in both views

## Data Available

**Dashboard:** `$appointment` loaded via `Appointment::withPortalRelations()` → `with(['patient.user', 'service', 'slot'])`

**Availability:** `$slot->appointments->first()` loaded via `->with(['appointments.patient.user'])` + `service` relation on appointment

## Modal Structure

Bootstrap modal, identical style to `#availabilityBatchModal`: `modal-dialog-centered modal-dialog-scrollable`.

**ID:** `appointmentInfoModal{{ $appointment->id }}` — unique per card.

**Trigger button:** small button inside `doctor-agenda-item__actions`, label "Informazioni", `data-bs-toggle="modal"`, `data-bs-target="#appointmentInfoModal{{ $appointment->id }}"`.

### Header
"Informazioni appuntamento" + close button

### Body

**Paziente**
- Nome: `$appointment->patientName()`
- Telefono: `$appointment->patient?->phone ?? '—'`
- Email: `$appointment->patient?->user?->email ?? '—'`
- Data di nascita: `$appointment->patient?->date_of_birth?->format('d/m/Y') ?? '—'`
- Codice fiscale: `$appointment->patient?->codice_fiscale ?? '—'`

**Prestazione**
- Nome: `$appointment->service?->name ?? '—'`
- Categoria: `match($appointment->service?->category) { 'VISITA' => 'Visita', 'ESAME' => 'Esame', default => '—' }`
- Durata: `$appointment->service?->duration_minutes ? $appointment->service->duration_minutes . ' min' : '—'`
- Prezzo: `$appointment->service?->price !== null ? 'EUR ' . number_format((float) $appointment->service->price, 2, ',', '.') : 'Da definire'`

**Note di prenotazione**
- `$appointment->notes ?? 'Nessuna nota'`

### Footer
Single "Chiudi" button (`data-bs-dismiss="modal"`, `btn-outline-secondary`)

## Modal Placement

Inside the `article.doctor-agenda-item` element, after the card body — same pattern as the treatment delete modal.

## Out of Scope

- No editing of patient info or notes from this modal
- No changes to booking or availability controllers
- No new routes or API endpoints
