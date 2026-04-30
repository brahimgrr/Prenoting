# Landing Page — Studio Dermatologico

**Date:** 2026-04-30
**Branch:** patient-feat-php

## Goal

Replace the current `GET /` redirect-to-login with a public landing page that presents the studio dermatologico and guides visitors toward login or registration.

## Layout

**Hero + 3 feature cards** (Bootstrap 5, Blade, no extra JS).

### Navbar
- Background: `--color-navy` (`#111827`)
- Left: brand mark (teal `M` square) + name "MedPortal" + subline "Studio Dermatologico"
- Right: two links — "Accedi" (ghost style) and "Nuovo Paziente" (blue primary, links to `/register`)

### Hero section
- Background: same gradient as `.auth-page` — `linear-gradient(135deg, rgba(15,118,110,.12), rgba(37,99,235,.08)), var(--color-canvas)`
- Eyebrow (uppercase teal): "Studio Dermatologico"
- H1: "La tua pelle, in buone mani"
- Subline (muted): "Prenota una visita dermatologica, gestisci i tuoi appuntamenti e accedi alla tua area personale."
- Two CTAs:
  - **"Accedi Area Personale"** → `/login` (btn-primary, blue)
  - **"Nuovo Paziente"** → `/register` (btn-outline-primary)

### Feature cards (3 columns)
Background `--color-canvas`, border `--color-border`, shadow `--shadow-panel`, border-radius 8px.

| Icon | Title | Description |
|------|-------|-------------|
| 🔬 | Visite Dermatologiche | Diagnosi e controllo della pelle con specialisti qualificati. |
| 🧴 | Trattamenti Estetici | Cura e benessere della pelle con trattamenti mirati. |
| 📋 | Mappatura Nei | Screening e prevenzione con tecnologia avanzata. |

### Footer
- Background `--color-canvas`, top border `--color-border`
- Text: "© 2025 MedPortal — Studio Dermatologico — Tutti i diritti riservati"

## Technical Implementation

### New file
`resources/views/landing.blade.php` — extends `layouts.guest`

### Route change
`routes/web.php` — `GET /`:
- **Authenticated users**: still redirect to `$user->portalRoute()` (no change)
- **Guests**: render `landing` view instead of redirecting to `/login`

### CSS
No new CSS classes needed — reuse existing design tokens and Bootstrap 5 utilities. Add a `.landing-page` wrapper class in `app.css` for hero/features/footer layout (flexbox/grid, padding, max-width centering).

### No JS required
Static HTML, no interactive elements beyond standard links.

## Out of scope
- No authentication logic changes
- No new routes (login and register routes are unchanged)
- No mobile-specific navbar (Bootstrap responsive collapse is acceptable for MVP)
