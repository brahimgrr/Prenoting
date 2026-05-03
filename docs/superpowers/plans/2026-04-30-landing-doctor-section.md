# Landing Page — Sezione Medico/Ambulatorio Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere una sezione "Chi ti segue" nella landing page pubblica che mostra foto, nome e info ambulatorio del medico dello studio dermatologico.

**Architecture:** Tre tocchi — route closure aggiornata per passare `DoctorProfile::first()` alla view, sezione HTML inserita in `landing.blade.php` dopo le feature card, CSS appeso in `app.css`. Foto: file statico `public/images/doctor.jpg` con fallback a iniziale se assente.

**Tech Stack:** Laravel 13, Blade, Bootstrap 5, CSS custom properties in `app.css`, PHPUnit feature tests.

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Modify | `tests/Feature/LandingPageTest.php` | Aggiunge 2 test per la sezione medico |
| Modify | `routes/web.php` line 17 | Passa `$doctor` alla view landing |
| Modify | `resources/views/landing.blade.php` | Aggiunge `<section class="landing-doctor">` dopo features |
| Modify | `resources/css/app.css` | Appende classi `.landing-doctor*` |

---

## Task 1: Aggiungi test fallenti per la sezione medico

**Files:**
- Modify: `tests/Feature/LandingPageTest.php`

- [ ] **Step 1: Aggiungi l'import di `DoctorProfile` e i due nuovi test in fondo alla classe**

Aggiungi `use App\Models\DoctorProfile;` agli import (riga 7) e i due metodi in fondo alla classe, prima della `}` finale:

```php
<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
  use RefreshDatabase;

  public function test_guest_sees_landing_page_at_root(): void
  {
    $this->get('/')->assertOk()->assertSee('La tua pelle');
  }

  public function test_landing_page_contains_login_and_register_links(): void
  {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="/login"', false);
    $response->assertSee('href="/register"', false);
  }

  public function test_authenticated_patient_is_redirected_to_portal(): void
  {
    $user = User::create([
      'username' => 'patient',
      'password' => Hash::make('patient123'),
      'role' => User::ROLE_PATIENT,
    ]);

    $this->actingAs($user)->get('/')->assertRedirect('/patient');
  }

  public function test_authenticated_doctor_is_redirected_to_portal(): void
  {
    $user = User::create([
      'username' => 'doctor',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);

    $this->actingAs($user)->get('/')->assertRedirect('/doctor');
  }

  public function test_landing_page_shows_chi_ti_segue_section(): void
  {
    $this->get('/')->assertOk()->assertSee('Chi ti segue');
  }

  public function test_landing_page_shows_doctor_display_name(): void
  {
    $user = User::create([
      'username' => 'doctor.derm',
      'password' => Hash::make('doctor123'),
      'role' => User::ROLE_DOCTOR,
    ]);
    DoctorProfile::create([
      'user_id' => $user->id,
      'display_name' => 'Dott. Giulia Ferretti',
    ]);

    $this->get('/')->assertOk()->assertSee('Dott. Giulia Ferretti');
  }
}
```

- [ ] **Step 2: Esegui i nuovi test per verificare che fallano**

```bash
docker compose exec app ./vendor/bin/phpunit tests/Feature/LandingPageTest.php
```

Expected: 2 FAIL (i nuovi test), 4 PASS (i test esistenti).

I nuovi test falliscono perché:
- La route non passa ancora `$doctor` alla view
- La sezione "Chi ti segue" non esiste ancora nel Blade

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/LandingPageTest.php
git commit -m "test: add failing tests for doctor section on landing page"
```

---

## Task 2: Aggiorna la route per passare il dottore

**Files:**
- Modify: `routes/web.php` lines 1–18

- [ ] **Step 1: Aggiungi l'import di `DoctorProfile` e aggiorna la closure**

Sostituisci il blocco della route root in `routes/web.php` (righe 14–18):

```php
Route::get('/', function () {
  $user = auth()->user();

  return $user ? redirect($user->portalRoute() ?? '/unsupported-role') : view('landing');
})->name('home');
```

Con:

```php
Route::get('/', function () {
  $user = auth()->user();

  if ($user) {
    return redirect($user->portalRoute() ?? '/unsupported-role');
  }

  return view('landing', [
    'doctor' => \App\Models\DoctorProfile::first(),
  ]);
})->name('home');
```

- [ ] **Step 2: Esegui i test — `test_landing_page_shows_doctor_display_name` passa, `test_landing_page_shows_chi_ti_segue_section` ancora fallisce**

```bash
docker compose exec app ./vendor/bin/phpunit tests/Feature/LandingPageTest.php
```

Expected: 1 FAIL (`test_landing_page_shows_chi_ti_segue_section` — la sezione non c'è ancora nel Blade), 5 PASS.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: pass DoctorProfile to landing view"
```

---

## Task 3: Aggiungi la sezione medico nel Blade

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Inserisci la sezione `landing-doctor` tra features e footer**

In `resources/views/landing.blade.php`, sostituisci il blocco `{{-- Footer --}}` con:

```blade
  {{-- Medico --}}
  <section class="landing-doctor">
    <span class="landing-doctor__eyebrow">Chi ti segue</span>
    <div class="landing-doctor__wrap">
      <div class="landing-doctor__card">
        @if (file_exists(public_path('images/doctor.jpg')))
          <img class="landing-doctor__photo" src="/images/doctor.jpg" alt="Foto {{ $doctor?->display_name ?? 'medico' }}">
        @else
          <div class="landing-doctor__photo landing-doctor__photo--placeholder">
            {{ strtoupper(substr($doctor?->display_name ?? 'M', 0, 1)) }}
          </div>
        @endif
        <div>
          <p class="landing-doctor__name">{{ $doctor?->display_name ?? 'Il nostro medico' }}</p>
          <p class="landing-doctor__title">Specialista in Dermatologia</p>
        </div>
        <div class="landing-doctor__divider"></div>
        <div class="landing-doctor__clinic">
          <span>🏥 <span>Via Roma 1, Milano</span></span>
          <span>📞 <span>02 1234567</span></span>
          <span>✉️ <span>info@studiodermatologo.it</span></span>
        </div>
      </div>
    </div>
  </section>

  {{-- Footer --}}
  <footer class="landing-footer">
    &copy; {{ date('Y') }} MedPortal &mdash; Studio Dermatologico &mdash; Tutti i diritti riservati
  </footer>
```

- [ ] **Step 2: Esegui tutti i test — tutti e 6 devono passare**

```bash
docker compose exec app ./vendor/bin/phpunit tests/Feature/LandingPageTest.php
```

Expected:
```
OK (6 tests, 11 assertions)
```

- [ ] **Step 3: Esegui la suite completa per verificare zero regressioni**

```bash
docker compose exec app ./vendor/bin/phpunit
```

Expected: tutti i test passano.

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: add doctor section to landing page"
```

---

## Task 4: Aggiungi CSS per la sezione medico

**Files:**
- Modify: `resources/css/app.css` (append alla fine, dopo il blocco `/* ── Landing page ── */`)

- [ ] **Step 1: Appendi il blocco CSS alla fine di `resources/css/app.css`**

```css
/* ── Landing doctor section ── */
.landing-doctor {
  background: var(--color-canvas);
  border-top: 1px solid var(--color-border);
  padding: 2.5rem 2rem;
  text-align: center;
}

.landing-doctor__eyebrow {
  color: var(--color-teal);
  display: block;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  margin-bottom: 1.25rem;
  text-transform: uppercase;
}

.landing-doctor__wrap {
  display: flex;
  justify-content: center;
}

.landing-doctor__card {
  align-items: center;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  box-shadow: var(--shadow-panel);
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-width: 400px;
  padding: 1.75rem 2.25rem;
  width: 100%;
}

.landing-doctor__photo {
  border: 3px solid var(--color-teal);
  border-radius: 50%;
  height: 96px;
  object-fit: cover;
  width: 96px;
}

.landing-doctor__photo--placeholder {
  align-items: center;
  background: #e0f5f2;
  color: var(--color-teal);
  display: flex;
  font-size: 2.5rem;
  font-weight: 800;
  justify-content: center;
}

.landing-doctor__name {
  color: var(--color-navy);
  font-size: 1.1rem;
  font-weight: 800;
  margin: 0;
}

.landing-doctor__title {
  color: var(--color-muted);
  font-size: 0.875rem;
  margin-top: 0.2rem;
}

.landing-doctor__divider {
  background: var(--color-border);
  border-radius: 2px;
  height: 2px;
  width: 40px;
}

.landing-doctor__clinic {
  color: var(--color-muted);
  display: flex;
  flex-direction: column;
  font-size: 0.875rem;
  gap: 0.4rem;
  text-align: center;
}

.landing-doctor__clinic span {
  align-items: center;
  display: flex;
  gap: 0.4rem;
  justify-content: center;
}

@media (max-width: 560px) {
  .landing-doctor {
    padding: 1.5rem 1rem;
  }

  .landing-doctor__card {
    padding: 1.25rem 1rem;
  }
}
```

- [ ] **Step 2: Build degli asset frontend**

```bash
npm run build
```

Expected: build completato senza errori.

- [ ] **Step 3: Verifica visiva**

Avvia l'app (`php artisan serve` oppure `docker compose up -d`) e apri `http://127.0.0.1:8000` (o `8080` con Docker).

Dovresti vedere in fondo alla landing:
- Titoletto "CHI TI SEGUE" in teal uppercase
- Card bianca centrata con bordo e ombra
- Cerchio teal con iniziale del medico (se `public/images/doctor.jpg` non esiste) oppure la foto
- Nome del medico in grassetto
- Separatore orizzontale
- Indirizzo, telefono, email in grigio

Per testare con la foto: copia un file JPG in `public/images/doctor.jpg` e ricarica la pagina.

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: add CSS for landing doctor section"
```
