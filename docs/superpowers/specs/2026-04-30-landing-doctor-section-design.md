# Landing Page — Sezione Medico/Ambulatorio

**Date:** 2026-04-30
**Branch:** patient-feat-php-merged

## Goal

Aggiungere una sezione "Chi ti segue" nella landing page pubblica (`GET /`) che presenti il medico dello studio dermatologico e le informazioni di contatto dell'ambulatorio.

## Layout

La sezione si inserisce **dopo** `.landing-features` (le 3 card) e **prima** del footer.

### Struttura HTML

```
<section class="landing-doctor">
  <span class="landing-doctor__eyebrow">Chi ti segue</span>
  <div class="landing-doctor__card">
    <img class="landing-doctor__photo" src="/images/doctor.jpg" alt="...">
    <p class="landing-doctor__name">{{ $doctor->display_name }}</p>
    <p class="landing-doctor__title">Specialista in Dermatologia</p>
    <div class="landing-doctor__divider"></div>
    <div class="landing-doctor__clinic">
      indirizzo, telefono, email
    </div>
  </div>
</section>
```

### Dati

| Campo | Fonte |
|-------|-------|
| Nome medico (`display_name`) | `DoctorProfile::first()` — passato dalla route closure |
| Foto | File statico `public/images/doctor.jpg` — caricato manualmente |
| Specialità | Hardcoded nella view: "Specialista in Dermatologia" |
| Indirizzo ambulatorio | Hardcoded nella view |
| Telefono | Hardcoded nella view |
| Email | Hardcoded nella view |

Se `public/images/doctor.jpg` non esiste, la `<img>` mostra il fallback alt text — non è un errore bloccante.

### Stile

- Sfondo `--color-canvas`, bordo top `--color-border`
- Card centrata, sfondo `--color-surface`, border-radius 12px, shadow panel
- Foto circolare 96×96px, bordo teal `--color-teal`
- Eyebrow teal uppercase, nome navy bold, info ambulatorio muted

## Modifiche tecniche

### `routes/web.php`
La route closure `GET /` passa `$doctor` alla view:

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

### `resources/views/landing.blade.php`
Aggiunta sezione `<section class="landing-doctor">` dopo `.landing-features`.

La foto viene resa con `<img>` se il file esiste, altrimenti con un div placeholder:
```blade
@if (file_exists(public_path('images/doctor.jpg')))
  <img class="landing-doctor__photo" src="/images/doctor.jpg" alt="Foto {{ $doctor?->display_name ?? 'medico' }}">
@else
  <div class="landing-doctor__photo landing-doctor__photo--placeholder">
    {{ strtoupper(substr($doctor?->display_name ?? 'M', 0, 1)) }}
  </div>
@endif
```

### `resources/css/app.css`
Aggiunta classi `.landing-doctor*` in append dopo il blocco landing esistente.

## Out of scope
- Upload foto dall'area admin
- Campo specialità nel DB
- Modello Clinic / ambulatorio nel DB
- Più medici
