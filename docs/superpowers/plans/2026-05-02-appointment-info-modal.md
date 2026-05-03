# Appointment Info Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an "Informazioni" button to every booked appointment card in the doctor's agenda (dashboard) and availability views, opening a Bootstrap modal with patient info, service info, and booking notes.

**Architecture:** Inline Bootstrap modal per card, identical pattern to the treatment delete modal. The availability controller needs one additional eager-load (`appointments.service`). No new routes, no JS.

**Tech Stack:** Laravel Blade, Bootstrap 5 modal

---

### Task 1: Add failing tests

**Files:**
- Modify: `tests/Feature/RoleDashboardTest.php`

- [ ] **Step 1: Extend the agenda test with modal assertions**

In `tests/Feature/RoleDashboardTest.php`, find `test_doctor_agenda_timeline_shows_selected_day_slots_with_inline_actions` (line 102). Change line:

```php
$this->appointment($patient, $service, $bookedSlot);
```

To (store the result):

```php
$bookedAppointment = $this->appointment($patient, $service, $bookedSlot);
```

Then append these assertions to the existing `->get("/doctor/schedule?date={$date}")` chain, right before the semicolon:

```php
->assertSee('Informazioni')
->assertSee("data-bs-target=\"#appointmentInfoModal{$bookedAppointment->id}\"", false)
->assertSee("id=\"appointmentInfoModal{$bookedAppointment->id}\"", false)
->assertSee('Informazioni appuntamento', false)
->assertSee('Note di prenotazione', false)
```

- [ ] **Step 2: Add a new availability test**

Add this new test method to `tests/Feature/RoleDashboardTest.php`, after `test_doctor_availability_is_a_dedicated_section`:

```php
public function test_doctor_availability_booked_slot_shows_appointment_info_modal(): void
{
    [$doctorUser, $doctor, $existingAppointment] = $this->dashboardContext();
    $patient = $existingAppointment->patient;
    $service = $existingAppointment->service;

    $slot = AvailabilitySlot::create([
        'start_at' => CarbonImmutable::now()->addDays(5)->setTime(14, 0),
        'end_at' => CarbonImmutable::now()->addDays(5)->setTime(14, 30),
        'is_booked' => true,
    ]);
    $appointment = Appointment::create([
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'slot_id' => $slot->id,
        'start_at' => $slot->start_at,
        'end_at' => $slot->end_at,
        'status' => Appointment::STATUS_CONFIRMED,
        'notes' => 'Visita di controllo annuale',
    ]);

    $this->actingAs($doctorUser)
        ->get('/doctor/availability')
        ->assertOk()
        ->assertSee('Informazioni')
        ->assertSee("data-bs-target=\"#appointmentInfoModal{$appointment->id}\"", false)
        ->assertSee("id=\"appointmentInfoModal{$appointment->id}\"", false)
        ->assertSee('Informazioni appuntamento', false)
        ->assertSee('Note di prenotazione', false)
        ->assertSee('Visita di controllo annuale', false);
}
```

- [ ] **Step 3: Run both failing tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Feature/RoleDashboardTest.php --filter "test_doctor_agenda_timeline|test_doctor_availability_booked_slot_shows_appointment_info_modal"
```

Expected: **FAIL** — modal elements not yet in the views.

---

### Task 2: Add appointments.service eager-load to availability controller

**Files:**
- Modify: `app/Http/Controllers/DoctorDashboardController.php` (line ~194)

- [ ] **Step 1: Add `appointments.service` to the availability query**

Find this line (around line 194):

```php
->with(['appointments.patient.user'])
```

Replace with:

```php
->with(['appointments.patient.user', 'appointments.service'])
```

---

### Task 3: Add info button and modal to dashboard (agenda)

**Files:**
- Modify: `resources/views/doctor/dashboard.blade.php` (lines 91–105)

- [ ] **Step 1: Replace the appointment card body + add modal**

Find this exact block:

```html
                    <article class="doctor-agenda-item doctor-agenda-item--{{ $itemClass }}">
                      @if ($appointment)
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $appointment->patientName() }}</h3>
                              <p>{{ $appointment->service?->name ?? 'Appuntamento' }}</p>
                            </div>
                          </div>
                          @if (! $slot)
                            <div class="doctor-agenda-item__meta">
                              <span>Slot non collegato</span>
                            </div>
                          @endif
                        </div>
```

Replace with:

```html
                    <article class="doctor-agenda-item doctor-agenda-item--{{ $itemClass }}">
                      @if ($appointment)
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $appointment->patientName() }}</h3>
                              <p>{{ $appointment->service?->name ?? 'Appuntamento' }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#appointmentInfoModal{{ $appointment->id }}"
                              >Informazioni</button>
                            </div>
                          </div>
                          @if (! $slot)
                            <div class="doctor-agenda-item__meta">
                              <span>Slot non collegato</span>
                            </div>
                          @endif
                        </div>
                        <div class="modal fade" id="appointmentInfoModal{{ $appointment->id }}" tabindex="-1" aria-labelledby="appointmentInfoModal{{ $appointment->id }}Label" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h2 class="modal-title h5" id="appointmentInfoModal{{ $appointment->id }}Label">Informazioni appuntamento</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                              </div>
                              <div class="modal-body">
                                <h3 class="fs-6 fw-semibold mb-2">Paziente</h3>
                                <dl class="row g-1 mb-3">
                                  <dt class="col-5">Nome</dt>
                                  <dd class="col-7">{{ $appointment->patientName() }}</dd>
                                  <dt class="col-5">Telefono</dt>
                                  <dd class="col-7">{{ $appointment->patient?->phone ?? '—' }}</dd>
                                  <dt class="col-5">Email</dt>
                                  <dd class="col-7">{{ $appointment->patient?->user?->email ?? '—' }}</dd>
                                  <dt class="col-5">Data di nascita</dt>
                                  <dd class="col-7">{{ $appointment->patient?->date_of_birth?->format('d/m/Y') ?? '—' }}</dd>
                                  <dt class="col-5">Codice fiscale</dt>
                                  <dd class="col-7">{{ $appointment->patient?->codice_fiscale ?? '—' }}</dd>
                                </dl>
                                <h3 class="fs-6 fw-semibold mb-2">Prestazione</h3>
                                <dl class="row g-1 mb-3">
                                  <dt class="col-5">Nome</dt>
                                  <dd class="col-7">{{ $appointment->service?->name ?? '—' }}</dd>
                                  <dt class="col-5">Categoria</dt>
                                  <dd class="col-7">{{ match($appointment->service?->category) { 'VISITA' => 'Visita', 'ESAME' => 'Esame', default => '—' } }}</dd>
                                  <dt class="col-5">Durata</dt>
                                  <dd class="col-7">{{ $appointment->service?->duration_minutes ? $appointment->service->duration_minutes . ' min' : '—' }}</dd>
                                  <dt class="col-5">Prezzo</dt>
                                  <dd class="col-7">{{ $appointment->service?->price !== null ? 'EUR ' . number_format((float) $appointment->service->price, 2, ',', '.') : 'Da definire' }}</dd>
                                </dl>
                                <h3 class="fs-6 fw-semibold mb-2">Note di prenotazione</h3>
                                <p class="mb-0">{{ $appointment->notes ?? 'Nessuna nota' }}</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
                              </div>
                            </div>
                          </div>
                        </div>
```

---

### Task 4: Add info button and modal to availability view

**Files:**
- Modify: `resources/views/doctor/availability.blade.php` (lines 103–136)

- [ ] **Step 1: Add `$infoAppt` to the @php block and replace badge with button + modal**

Find this exact `@php` block:

```php
                    @php
                      $isFree = ! $slot->is_booked && ! $slot->is_blocked;
                      $isBooked = $slot->is_booked;
                      $patientName = $isBooked ? $slot->appointments->first()?->patientName() : null;
                      $slotStateClass = $isFree ? 'free' : ($isBooked ? 'appointment' : 'blocked');
                      $slotLabel = $isFree ? 'Slot libero' : ($isBooked ? ($patientName ?? 'Slot prenotato') : 'Slot bloccato');
                    @endphp
```

Replace with:

```php
                    @php
                      $isFree = ! $slot->is_booked && ! $slot->is_blocked;
                      $isBooked = $slot->is_booked;
                      $infoAppt = $isBooked ? $slot->appointments->first() : null;
                      $patientName = $infoAppt?->patientName();
                      $slotStateClass = $isFree ? 'free' : ($isBooked ? 'appointment' : 'blocked');
                      $slotLabel = $isFree ? 'Slot libero' : ($isBooked ? ($patientName ?? 'Slot prenotato') : 'Slot bloccato');
                    @endphp
```

- [ ] **Step 2: Replace the badge with an info button and add modal**

Find this block:

```html
                    <div class="avail-slot-row">
                      <article class="doctor-agenda-item doctor-agenda-item--{{ $slotStateClass }}">
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</h3>
                              <p>{{ $slotLabel }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              @if ($isFree)
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                                </form>
                              @elseif ($isBooked)
                                <span class="badge text-bg-primary">Prenotato</span>
                              @else
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                                </form>
                              @endif
                            </div>
                          </div>
                        </div>
                      </article>
                    </div>
```

Replace with:

```html
                    <div class="avail-slot-row">
                      <article class="doctor-agenda-item doctor-agenda-item--{{ $slotStateClass }}">
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</h3>
                              <p>{{ $slotLabel }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              @if ($isFree)
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                                </form>
                              @elseif ($isBooked && $infoAppt)
                                <button
                                  class="btn btn-sm btn-outline-secondary"
                                  type="button"
                                  data-bs-toggle="modal"
                                  data-bs-target="#appointmentInfoModal{{ $infoAppt->id }}"
                                >Informazioni</button>
                              @else
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                                </form>
                              @endif
                            </div>
                          </div>
                        </div>
                        @if ($isBooked && $infoAppt)
                          <div class="modal fade" id="appointmentInfoModal{{ $infoAppt->id }}" tabindex="-1" aria-labelledby="appointmentInfoModal{{ $infoAppt->id }}Label" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h2 class="modal-title h5" id="appointmentInfoModal{{ $infoAppt->id }}Label">Informazioni appuntamento</h2>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                                </div>
                                <div class="modal-body">
                                  <h3 class="fs-6 fw-semibold mb-2">Paziente</h3>
                                  <dl class="row g-1 mb-3">
                                    <dt class="col-5">Nome</dt>
                                    <dd class="col-7">{{ $infoAppt->patientName() }}</dd>
                                    <dt class="col-5">Telefono</dt>
                                    <dd class="col-7">{{ $infoAppt->patient?->phone ?? '—' }}</dd>
                                    <dt class="col-5">Email</dt>
                                    <dd class="col-7">{{ $infoAppt->patient?->user?->email ?? '—' }}</dd>
                                    <dt class="col-5">Data di nascita</dt>
                                    <dd class="col-7">{{ $infoAppt->patient?->date_of_birth?->format('d/m/Y') ?? '—' }}</dd>
                                    <dt class="col-5">Codice fiscale</dt>
                                    <dd class="col-7">{{ $infoAppt->patient?->codice_fiscale ?? '—' }}</dd>
                                  </dl>
                                  <h3 class="fs-6 fw-semibold mb-2">Prestazione</h3>
                                  <dl class="row g-1 mb-3">
                                    <dt class="col-5">Nome</dt>
                                    <dd class="col-7">{{ $infoAppt->service?->name ?? '—' }}</dd>
                                    <dt class="col-5">Categoria</dt>
                                    <dd class="col-7">{{ match($infoAppt->service?->category) { 'VISITA' => 'Visita', 'ESAME' => 'Esame', default => '—' } }}</dd>
                                    <dt class="col-5">Durata</dt>
                                    <dd class="col-7">{{ $infoAppt->service?->duration_minutes ? $infoAppt->service->duration_minutes . ' min' : '—' }}</dd>
                                    <dt class="col-5">Prezzo</dt>
                                    <dd class="col-7">{{ $infoAppt->service?->price !== null ? 'EUR ' . number_format((float) $infoAppt->service->price, 2, ',', '.') : 'Da definire' }}</dd>
                                  </dl>
                                  <h3 class="fs-6 fw-semibold mb-2">Note di prenotazione</h3>
                                  <p class="mb-0">{{ $infoAppt->notes ?? 'Nessuna nota' }}</p>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
                                </div>
                              </div>
                            </div>
                          </div>
                        @endif
                      </article>
                    </div>
```

---

### Task 5: Run tests and commit

- [ ] **Step 1: Run the two target tests — should pass**

```bash
./vendor/bin/phpunit tests/Feature/RoleDashboardTest.php --filter "test_doctor_agenda_timeline|test_doctor_availability_booked_slot_shows_appointment_info_modal"
```

Expected: **PASS**

- [ ] **Step 2: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: all tests **PASS**

- [ ] **Step 3: Commit**

```bash
git add resources/views/doctor/dashboard.blade.php \
        resources/views/doctor/availability.blade.php \
        app/Http/Controllers/DoctorDashboardController.php \
        tests/Feature/RoleDashboardTest.php
git commit -m "feat: add appointment info modal to agenda and availability"
```
