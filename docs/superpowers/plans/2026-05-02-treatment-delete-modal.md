# Treatment Delete Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the native `window.confirm()` dialog on "Elimina trattamento" with a Bootstrap modal identical in structure to the patient's appointment cancellation modal.

**Architecture:** Single view file change — `treatments.blade.php`. The delete button in the dropdown becomes a modal trigger; the form (with DELETE method) moves inside the modal. Each card gets its own modal with a unique ID (`deleteTreatmentModal{{ $offering->id }}`), matching the pattern used by `appointment-card.blade.php`.

**Tech Stack:** Laravel Blade, Bootstrap 5 modal

---

### Task 1: Add failing test assertions for the modal

**Files:**
- Modify: `tests/Feature/RoleDashboardTest.php` (around line 462–474)

The existing test `test_doctor_can_manage_treatment_offerings` already asserts the treatments page renders. Extend it to also assert the modal elements are present.

- [ ] **Step 1: Add assertions to the existing GET `/doctor/treatments` block**

In `tests/Feature/RoleDashboardTest.php`, find the block starting at line 462:

```php
$this->actingAs($doctorUser)
    ->get('/doctor/treatments')
    ->assertOk()
    ->assertSee('aria-label="Azioni trattamento"', false)
    ->assertSee('data-bs-toggle="dropdown"', false)
    ->assertSee('Modifica trattamento')
    ->assertSee("href=\"/doctor/treatments/{$offering->id}/edit\"", false)
    ->assertSee('Elimina trattamento')
    ->assertSee("action=\"/doctor/treatments/{$offering->id}\"", false)
    ->assertDontSee('class="treatment-card__actions"', false)
    ->assertDontSee('Attivo')
    ->assertDontSee('Disattiva')
    ->assertDontSee('Durata');
```

Replace it with:

```php
$this->actingAs($doctorUser)
    ->get('/doctor/treatments')
    ->assertOk()
    ->assertSee('aria-label="Azioni trattamento"', false)
    ->assertSee('data-bs-toggle="dropdown"', false)
    ->assertSee('Modifica trattamento')
    ->assertSee("href=\"/doctor/treatments/{$offering->id}/edit\"", false)
    ->assertSee('Elimina trattamento')
    ->assertSee("action=\"/doctor/treatments/{$offering->id}\"", false)
    ->assertDontSee('class="treatment-card__actions"', false)
    ->assertDontSee('Attivo')
    ->assertDontSee('Disattiva')
    ->assertDontSee('Durata')
    ->assertSee("data-bs-target=\"#deleteTreatmentModal{$offering->id}\"", false)
    ->assertSee("id=\"deleteTreatmentModal{$offering->id}\"", false)
    ->assertSee('Conferma eliminazione', false)
    ->assertSee('Gli appuntamenti già prenotati resteranno validi.', false)
    ->assertDontSee('window.confirm', false);
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test tests/Feature/RoleDashboardTest.php --filter test_doctor_can_manage_treatment_offerings
```

Expected: **FAIL** — modal elements not yet present in the view.

---

### Task 2: Implement the modal in the treatments view

**Files:**
- Modify: `resources/views/doctor/treatments.blade.php` (lines 70–89)

- [ ] **Step 1: Replace the delete form+button with a modal trigger + modal**

Find this block inside the `@foreach ($offerings as $offering)` loop (lines 78–89):

```html
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a href="/doctor/treatments/{{ $offering->id }}/edit" class="dropdown-item">Modifica trattamento</a>
                    </li>
                    <li>
                      <form method="POST" action="/doctor/treatments/{{ $offering->id }}" onsubmit="return window.confirm('Eliminare questo trattamento?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">Elimina trattamento</button>
                      </form>
                    </li>
                  </ul>
```

Replace with:

```html
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a href="/doctor/treatments/{{ $offering->id }}/edit" class="dropdown-item">Modifica trattamento</a>
                    </li>
                    <li>
                      <button
                        class="dropdown-item text-danger"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteTreatmentModal{{ $offering->id }}"
                      >Elimina trattamento</button>
                    </li>
                  </ul>
```

- [ ] **Step 2: Add the modal inside the `article.treatment-card`, after the closing `</dl>` tag**

Find the closing of each card (around line 98):

```html
            </article>
```

Replace with:

```html
              <div class="modal fade" id="deleteTreatmentModal{{ $offering->id }}" tabindex="-1" aria-labelledby="deleteTreatmentModal{{ $offering->id }}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="POST" action="/doctor/treatments/{{ $offering->id }}">
                      @csrf
                      @method('DELETE')
                      <div class="modal-header">
                        <h2 class="modal-title fs-5" id="deleteTreatmentModal{{ $offering->id }}Label">Conferma eliminazione</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi conferma eliminazione"></button>
                      </div>
                      <div class="modal-body">
                        <p>Questo trattamento verrà rimosso. Gli appuntamenti già prenotati resteranno validi.</p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger">Sì, elimina</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </article>
```

- [ ] **Step 3: Run the target test — should pass**

```bash
php artisan test tests/Feature/RoleDashboardTest.php --filter test_doctor_can_manage_treatment_offerings
```

Expected: **PASS**

- [ ] **Step 4: Run the full test suite to check for regressions**

```bash
php artisan test
```

Expected: all tests **PASS**

- [ ] **Step 5: Commit**

```bash
git add resources/views/doctor/treatments.blade.php \
        tests/Feature/RoleDashboardTest.php
git commit -m "feat: replace window.confirm with Bootstrap modal on treatment delete"
```
