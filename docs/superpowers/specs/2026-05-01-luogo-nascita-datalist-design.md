# Luogo di nascita — Datalist dropdown

**Date:** 2026-05-01

## Goal

Replace the free-text `place_of_birth` input on the registration form with a native HTML `<datalist>` backed by all entries in `ComuniItaliani.php` (comuni italiani + stati esteri, ~8.213 voci). The user types to filter suggestions; the browser handles the UI natively.

## Scope

- `AuthController::showRegister()` — pass comuni names to the view
- `resources/views/auth/register.blade.php` — add `list` attribute to input, render `<datalist>`
- No new routes, no JS, no libraries, no validation changes

## Data

`app/Data/ComuniItaliani.php` returns an associative array keyed by uppercase ASCII names (e.g. `'REGGIO CALABRIA' => 'F158'`). The keys are the display values shown in the datalist.

## Controller change

```php
public function showRegister(): View
{
    $comuni = array_keys(require app_path('Data/ComuniItaliani.php'));
    return view('auth.register', compact('comuni'));
}
```

## View change

The `place_of_birth` input gains `list="comuni-list"`. The hint text is removed (the datalist guides the user). A `<datalist>` is rendered with all keys.

```html
<input ... list="comuni-list">
<datalist id="comuni-list">
    @foreach($comuni as $comune)
        <option value="{{ $comune }}">
    @endforeach
</datalist>
```

## Validation

Unchanged. `CodiceFiscaleService::normalizzaLuogo()` already normalizes input to uppercase ASCII before the lookup, so any casing the user types (or selects from the datalist) is handled correctly.

## Out of scope

- Profile edit page (`patient/profile.blade.php`) — `place_of_birth` is read-only there, no change needed.
- Fuzzy matching / partial word search — native `<datalist>` prefix-matches from the start of each option. Acceptable for this MVP.
