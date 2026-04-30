# Landing Page — Studio Dermatologico Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the guest redirect on `GET /` with a public landing page for the studio dermatologico.

**Architecture:** Three touches — one new Blade view, one route tweak (swap `redirect('/login')` for `view('landing')`), and CSS appended to `app.css`. No new controllers, no JS.

**Tech Stack:** Laravel 13, Blade, Bootstrap 5, custom CSS variables in `app.css`, PHPUnit feature tests.

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `tests/Feature/LandingPageTest.php` | HTTP tests for the root route |
| Modify | `routes/web.php` line 17 | Swap `redirect('/login')` → `view('landing')` |
| Create | `resources/views/landing.blade.php` | The landing page view |
| Modify | `resources/css/app.css` | Append `.landing-*` CSS classes |

---

## Task 1: Write failing feature tests

**Files:**
- Create: `tests/Feature/LandingPageTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature;

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
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/LandingPageTest.php
```

Expected: all 4 tests FAIL — the guest tests fail because `/` returns a redirect, not a 200 with view content. The auth tests may pass already (the existing redirect logic is correct). That is fine — the auth redirect tests failing proves nothing is broken yet.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/LandingPageTest.php
git commit -m "test: add failing landing page feature tests"
```

---

## Task 2: Update the root route

**Files:**
- Modify: `routes/web.php` line 17

- [ ] **Step 1: Change `redirect('/login')` to `view('landing')` in the root closure**

In `routes/web.php`, find this block (lines 14–18):

```php
Route::get('/', function () {
  $user = auth()->user();

  return $user ? redirect($user->portalRoute() ?? '/unsupported-role') : redirect('/login');
});
```

Replace with:

```php
Route::get('/', function () {
  $user = auth()->user();

  return $user ? redirect($user->portalRoute() ?? '/unsupported-role') : view('landing');
});
```

- [ ] **Step 2: Create a minimal view so Laravel doesn't throw a "view not found" error**

Create `resources/views/landing.blade.php` with just enough to let the route resolve:

```blade
@extends('layouts.guest', ['title' => 'Studio Dermatologico — MedPortal'])

@section('content')
<p>La tua pelle, in buone mani</p>
@endsection
```

- [ ] **Step 3: Run tests — 3 of 4 should pass**

```bash
php artisan test tests/Feature/LandingPageTest.php
```

Expected: 3 PASS, 1 FAIL.
- ✅ `test_guest_sees_landing_page_at_root` — passes (minimal view contains "La tua pelle")
- ❌ `test_landing_page_contains_login_and_register_links` — still fails (links not in minimal view yet)
- ✅ `test_authenticated_patient_is_redirected_to_portal` — passes
- ✅ `test_authenticated_doctor_is_redirected_to_portal` — passes

The remaining failure is expected and will be fixed in Task 3.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php resources/views/landing.blade.php
git commit -m "feat: show landing page to guests at root route"
```

---

## Task 3: Build the full landing view

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Replace the minimal stub with the full Blade template**

Overwrite `resources/views/landing.blade.php`:

```blade
@extends('layouts.guest', ['title' => 'Studio Dermatologico — MedPortal'])

@section('content')
<div class="landing-page">

  {{-- Navbar --}}
  <nav class="landing-nav">
    <a class="landing-brand" href="/">
      <span class="app-brand__mark">M</span>
      <span>
        <span class="landing-brand__name">MedPortal</span>
        <span class="landing-brand__sub">Studio Dermatologico</span>
      </span>
    </a>
    <div class="landing-nav__links">
      <a class="landing-nav__link landing-nav__link--ghost" href="/login">Accedi</a>
      <a class="landing-nav__link landing-nav__link--primary" href="/register">Nuovo Paziente</a>
    </div>
  </nav>

  {{-- Hero --}}
  <section class="landing-hero">
    <span class="portal-eyebrow">Studio Dermatologico</span>
    <h1>La tua pelle,<br>in buone mani</h1>
    <p>Prenota una visita dermatologica, gestisci i tuoi appuntamenti e accedi alla tua area personale.</p>
    <div class="landing-cta">
      <a class="btn btn-primary btn-lg" href="/login">Accedi Area Personale</a>
      <a class="btn btn-outline-primary btn-lg" href="/register">Nuovo Paziente</a>
    </div>
  </section>

  {{-- Features --}}
  <section class="landing-features">
    <div class="landing-card">
      <span class="landing-card__icon">🔬</span>
      <h3>Visite Dermatologiche</h3>
      <p>Diagnosi e controllo della pelle con specialisti qualificati.</p>
    </div>
    <div class="landing-card">
      <span class="landing-card__icon">🧴</span>
      <h3>Trattamenti Estetici</h3>
      <p>Cura e benessere della pelle con trattamenti mirati.</p>
    </div>
    <div class="landing-card">
      <span class="landing-card__icon">📋</span>
      <h3>Mappatura Nei</h3>
      <p>Screening e prevenzione con tecnologia avanzata.</p>
    </div>
  </section>

  {{-- Footer --}}
  <footer class="landing-footer">
    &copy; 2025 MedPortal &mdash; Studio Dermatologico &mdash; Tutti i diritti riservati
  </footer>

</div>
@endsection
```

- [ ] **Step 2: Run all landing tests — all 4 must pass**

```bash
php artisan test tests/Feature/LandingPageTest.php
```

Expected output:
```
PASS  Tests\Feature\LandingPageTest
✓ guest sees landing page at root
✓ landing page contains login and register links
✓ authenticated patient is redirected to portal
✓ authenticated doctor is redirected to portal
```

- [ ] **Step 3: Run full test suite to check for regressions**

```bash
php artisan test
```

Expected: all existing tests still pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: build full landing page for studio dermatologico"
```

---

## Task 4: Add landing page CSS

**Files:**
- Modify: `resources/css/app.css` (append at end)

- [ ] **Step 1: Append the landing CSS block to the end of `resources/css/app.css`**

```css
/* ── Landing page ── */
.landing-page {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.landing-nav {
  align-items: center;
  background: var(--color-navy);
  display: flex;
  justify-content: space-between;
  padding: 0.875rem 1.75rem;
}

.landing-brand {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  text-decoration: none;
}

.landing-brand__name {
  color: #ffffff;
  display: block;
  font-size: 1rem;
  font-weight: 800;
}

.landing-brand__sub {
  color: #94a3b8;
  display: block;
  font-size: 0.78rem;
}

.landing-nav__links {
  display: flex;
  gap: 0.5rem;
}

.landing-nav__link {
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 650;
  padding: 0.45rem 1rem;
  text-decoration: none;
}

.landing-nav__link--ghost {
  background: rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
}

.landing-nav__link--ghost:hover {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

.landing-nav__link--primary {
  background: var(--color-blue);
  color: #ffffff;
}

.landing-nav__link--primary:hover {
  background: #1d4ed8;
  color: #ffffff;
}

.landing-hero {
  background:
    linear-gradient(135deg, rgba(15, 118, 110, 0.12), rgba(37, 99, 235, 0.08)),
    var(--color-canvas);
  padding: 4rem 2rem 3.5rem;
  text-align: center;
}

.landing-hero h1 {
  color: var(--color-navy);
  font-size: clamp(2rem, 5vw, 2.8rem);
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 0.875rem;
}

.landing-hero > p {
  color: var(--color-muted);
  font-size: 1rem;
  line-height: 1.6;
  margin: 0 auto 1.75rem;
  max-width: 460px;
}

.landing-cta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: center;
}

.landing-features {
  background: var(--color-surface);
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: center;
  padding: 2.5rem 2rem;
}

.landing-card {
  background: var(--color-canvas);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  box-shadow: var(--shadow-panel);
  flex: 1;
  max-width: 240px;
  min-width: 180px;
  padding: 1.5rem 1.25rem;
  text-align: center;
}

.landing-card__icon {
  display: block;
  font-size: 2rem;
  margin-bottom: 0.75rem;
}

.landing-card h3 {
  color: var(--color-navy);
  font-size: 0.95rem;
  font-weight: 800;
  margin-bottom: 0.4rem;
}

.landing-card p {
  color: var(--color-muted);
  font-size: 0.82rem;
  line-height: 1.5;
  margin: 0;
}

.landing-footer {
  background: var(--color-canvas);
  border-top: 1px solid var(--color-border);
  color: var(--color-muted);
  font-size: 0.78rem;
  padding: 0.875rem 1.5rem;
  text-align: center;
}

@media (max-width: 560px) {
  .landing-nav {
    padding: 0.75rem 1rem;
  }

  .landing-hero {
    padding: 2.5rem 1rem 2rem;
  }

  .landing-features {
    padding: 1.5rem 1rem;
  }

  .landing-card {
    max-width: none;
    min-width: 100%;
  }
}
```

- [ ] **Step 2: Build frontend assets**

```bash
npm run build
```

Expected: build completes without errors, `public/build/` updated.

- [ ] **Step 3: Verify the page visually**

Start the dev server (if not already running):

```bash
php artisan serve
```

Open `http://127.0.0.1:8000` in a browser while **not** logged in. You should see:
- Navy navbar with teal "M" mark, "MedPortal" + "Studio Dermatologico" subline
- Teal+blue gradient hero with "La tua pelle, in buone mani"
- Two CTA buttons: "Accedi Area Personale" (blue) and "Nuovo Paziente" (outlined)
- Three feature cards on white background
- Footer

Log in as any user (`admin` / `admin123`) and navigate to `/` — you should be redirected to `/admin`.

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: add landing page CSS using existing design tokens"
```
