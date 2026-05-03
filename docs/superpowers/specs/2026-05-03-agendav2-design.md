# Agendav2 — Design Spec

**Date:** 2026-05-03
**Status:** Approved

---

## Goal

Create a new doctor section `/doctor/agendav2` that merges the existing "Agenda" (`/doctor/schedule`) and "Disponibilità" (`/doctor/availability`) sections into one self-contained view. The new section is experimental and may eventually replace both existing sections entirely, so it must be **feature-complete** with no dependency on the old views.

---

## Layout & UX

Following the approved mockup and the existing portal design language (Bootstrap grid + minimal custom CSS).

**Page structure (top to bottom):**

1. **Header row** — eyebrow "Portale medico", h1 "Agenda", right-aligned "Crea disponibilità" button
2. **3 stat cards** — Bootstrap `row g-3 mb-3` with three `col-4` panels using existing `.portal-panel`:
   - Appuntamenti (count of appointments for selected day)
   - Slot disponibili (count of free, non-blocked, non-booked slots for selected day)
   - Fatturato (sum of `appointment->service->price` for selected day, formatted as `€ X`)
3. **Agenda panel** — `.portal-panel` with:
   - Panel header: title "Agenda del giorno" + selected day label on the left; date-nav (prev arrow, date input, next arrow) on the right
   - Scrollable timeline grid using all existing `.doctor-agenda-*` classes and the `agendaRows` data structure (identical to current dashboard)
   - Now-marker, block/unblock slot actions, appointment info button — all identical to current dashboard
4. **Appointment info modals** — `@push('modals')` pattern, identical to dashboard
5. **Crea disponibilità modal** — Bootstrap modal, identical content to current `availability.blade.php` modal (form screen + preview screen)

---

## Architecture

### New files

| File | Action |
|------|--------|
| `resources/views/doctor/agendav2.blade.php` | Create — self-contained, no @include from other doctor views |

### Modified files

| File | Change |
|------|--------|
| `app/Http/Controllers/DoctorDashboardController.php` | Add `agendaV2()` public method + `viewAgendaV2()` private method; modify `previewAvailability()` and `storeAvailabilityBatch()` to handle `source=agendav2` |
| `routes/web.php` | Add `GET /doctor/agendav2` route |
| `resources/views/layouts/portal.blade.php` | Add "Agenda v2" nav item for doctor role |

---

## Controller

### `agendaV2(Request $request): View`

Public entry point. Delegates to `viewAgendaV2($request)`.

### `viewAgendaV2(Request $request, ?array $availabilityPreview = null): View`

Loads all data needed by the merged view:

**Schedule data (same as `viewSchedule`):**
- `$selectedDate` from `?date=` query param, defaults to today
- `$appointments` — `Appointment::withPortalRelations()->whereDate('start_at', $selectedDate)->orderBy('start_at')->get()`
- `$daySlots` — `AvailabilitySlot::whereDate('start_at', $selectedDate)->orderBy('start_at')->get()`
- `$agendaRows` — built via existing `buildAgendaRows()`
- `$currentTime`, `$isSelectedToday`

**Stat derivations:**
- `$appointmentCount` — `$appointments->count()`
- `$dayFreeSlots` — `$daySlots->filter(fn($s) => !$s->is_booked && !$s->is_blocked)->count()`
- `$fatturato` — `$appointments->sum(fn($a) => $a->service?->price ?? 0)`

**Availability modal data (same as `viewAvailability`):**
- `$batchForm` — defaults or from preview input
- `$availabilityPreview` — passed in or null

Returns `view('doctor.agendav2', [...all above...])`.

### Modified: `previewAvailability(Request $request)`

Add: if `$request->query('source') === 'agendav2'`, call `viewAgendaV2($request, $preview)` instead of `viewAvailability($request, $preview)`.

### Modified: `storeAvailabilityBatch(Request $request)`

Add: if `$request->input('_source') === 'agendav2'`, redirect to `/doctor/agendav2` instead of `/doctor/availability`.

---

## View: `agendav2.blade.php`

**Stack:** Laravel Blade, Bootstrap (existing Vite bundle), reuses existing CSS classes only — no new CSS.

**Classes reused:**
- `.portal-section`, `.portal-page-heading`, `.portal-heading-row`, `.portal-eyebrow` — page chrome
- `.portal-panel` — card container
- `.dashboard-day-nav`, `.dashboard-day-nav__controls`, `.dashboard-day-nav__arrow`, `.dashboard-date-filter` — date navigation
- `.doctor-agenda-scroll`, `.doctor-agenda-grid`, `.doctor-agenda-row`, `.doctor-agenda-row--past`, `.doctor-agenda-item`, `.doctor-agenda-item--*`, `.doctor-agenda-item__body`, `.doctor-agenda-item__main`, `.doctor-agenda-item__actions`, `.doctor-agenda-now-marker` — timeline
- `.avail-*` — availability modal (already defined in app.css)
- Bootstrap: `row`, `col-4`, `g-3`, `mb-3`, `h-100`, `p-3`, `d-block`, `fs-2`, `lh-1`, `mt-2`, `text-body-secondary`, `small`, `fw-bold`, `text-uppercase`, `btn`, `btn-*`, `modal`, `modal-*`, `badge`, `text-bg-*`

**Availability modal:** Copy the full modal markup from `availability.blade.php` verbatim (same form, same JS functions `availOpenModal`, `availCloseModal`, etc.). Add two hidden fields:
- In the preview form: `<input type="hidden" name="source" value="agendav2">`
- In the batch confirm form: `<input type="hidden" name="_source" value="agendav2">`

**Day navigation URL helpers** (same as dashboard):
```php
$previousDayUrl = request()->fullUrlWithQuery(['date' => $selectedDay->subDay()->toDateString()]);
$nextDayUrl = request()->fullUrlWithQuery(['date' => $selectedDay->addDay()->toDateString()]);
```

---

## Route

```php
Route::get('/doctor/agendav2', [DoctorDashboardController::class, 'agendaV2']);
```

Added after the existing `/doctor/schedule` route.

---

## Navigation

In `layouts/portal.blade.php`, doctor nav array:

```php
['to' => '/doctor/agendav2', 'label' => 'Agenda v2', 'active' => ['doctor/agendav2']],
```

Added after the existing 'Agenda' item.

---

## What is NOT included

- No changes to existing `/doctor/schedule` or `/doctor/availability` routes or views
- No tests added (experimental section, can be added when promoted)
- No new CSS classes — all styling via Bootstrap + existing project classes
- The multi-day future availability tab strip (from `availability.blade.php`) is **not** included in agendav2; slot management happens inline via block/unblock in the day timeline

---

## Future: replacing existing sections

When agendav2 is promoted to replace both sections:
1. Remove "Agenda" and "Disponibilità" nav items, rename "Agenda v2" → "Agenda"
2. Change route from `/doctor/agendav2` → `/doctor/schedule` (or keep and redirect)
3. Remove `source` branching in controller — agendav2 becomes the only path
4. Archive or delete `dashboard.blade.php` and `availability.blade.php`
