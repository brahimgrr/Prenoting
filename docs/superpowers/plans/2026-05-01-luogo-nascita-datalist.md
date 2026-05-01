# Luogo di Nascita Datalist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the free-text `place_of_birth` input on the registration form with a native HTML `<datalist>` backed by all ~8.213 entries in `ComuniItaliani.php`.

**Architecture:** The controller loads comuni keys and passes them to the view; the view renders a `<datalist>` element; the input gains a `list` attribute. No JS, no new routes, no validation changes.

**Tech Stack:** Laravel (Blade views, controller), Bootstrap, native HTML `<datalist>`

---

### Task 1: Add failing test for datalist on register page

**Files:**
- Modify: `tests/Feature/AuthTest.php`

- [ ] **Step 1: Add the failing test**

Open `tests/Feature/AuthTest.php` and add this test method inside the `AuthTest` class (after the last test):

```php
public function test_register_page_shows_datalist_with_comuni(): void
{
    $response = $this->get('/register');

    $response->assertOk();
    $response->assertSee('<datalist id="comuni-list">', false);
    $response->assertSee('<option value="ROMA">', false);
    $response->assertSee('<option value="MILANO">', false);
    $response->assertSee('<option value="FRANCIA">', false);
    $response->assertSee('list="comuni-list"', false);
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test tests/Feature/AuthTest.php --filter test_register_page_shows_datalist_with_comuni
```

Expected output: **FAIL** — the datalist does not exist yet.

---

### Task 2: Pass comuni to the register view

**Files:**
- Modify: `app/Http/Controllers/AuthController.php:51-53`

- [ ] **Step 1: Update `showRegister()` to load and pass comuni**

Replace:
```php
public function showRegister(): View
{
    return view('auth.register');
}
```

With:
```php
public function showRegister(): View
{
    $comuni = array_keys(require app_path('Data/ComuniItaliani.php'));
    return view('auth.register', compact('comuni'));
}
```

- [ ] **Step 2: Run the test — still fails (view not updated yet)**

```bash
php artisan test tests/Feature/AuthTest.php --filter test_register_page_shows_datalist_with_comuni
```

Expected output: **FAIL** — datalist still missing from the view.

---

### Task 3: Add datalist to register view

**Files:**
- Modify: `resources/views/auth/register.blade.php:63-67`

- [ ] **Step 1: Replace the `place_of_birth` input block**

Replace this block (lines 57–67):
```html
<div class="col-md-6">
  <label class="form-label" for="place_of_birth">Luogo di nascita</label>
  <input class="form-control @error('place_of_birth') is-invalid @enderror" id="place_of_birth" name="place_of_birth" autocomplete="address-level2" value="{{ old('place_of_birth') }}" required>
  @error('place_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
  <div class="form-text">Scrivi il nome per esteso (es. Reggio Calabria). Per nati all'estero, scrivi il nome del paese in italiano.</div>
</div>
```

With:
```html
<div class="col-md-6">
  <label class="form-label" for="place_of_birth">Luogo di nascita</label>
  <input class="form-control @error('place_of_birth') is-invalid @enderror" id="place_of_birth" name="place_of_birth" list="comuni-list" autocomplete="off" value="{{ old('place_of_birth') }}" required>
  @error('place_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
  <datalist id="comuni-list">
    @foreach($comuni as $comune)
      <option value="{{ $comune }}">
    @endforeach
  </datalist>
</div>
```

- [ ] **Step 2: Run the new test — should pass now**

```bash
php artisan test tests/Feature/AuthTest.php --filter test_register_page_shows_datalist_with_comuni
```

Expected output: **PASS**

- [ ] **Step 3: Run the full auth test suite to confirm no regressions**

```bash
php artisan test tests/Feature/AuthTest.php
```

Expected output: all tests **PASS**

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AuthController.php \
        resources/views/auth/register.blade.php \
        tests/Feature/AuthTest.php
git commit -m "feat: add datalist for luogo di nascita on registration form"
```
