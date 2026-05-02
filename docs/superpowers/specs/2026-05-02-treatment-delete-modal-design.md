# Treatment Delete Modal — Design Spec

**Date:** 2026-05-02

## Goal

Replace the native `window.confirm()` dialog on the doctor's "Elimina trattamento" action with a Bootstrap modal identical in structure to the patient's appointment cancellation modal.

## Scope

- `resources/views/doctor/treatments.blade.php` — only file changed
- No controller changes, no new routes, no JS

## Reference: Patient Modal Pattern

`resources/views/patient/partials/appointment-card.blade.php` is the reference. Each `article.appointment-card` contains its own Bootstrap modal with a unique ID (`appointmentCancelModal{{ $appointment->id }}`). The modal trigger is a `<button>` in the dropdown with `data-bs-toggle="modal"` and `data-bs-target`. The form lives inside the modal.

## Change: Doctor Treatments View

For each `article.treatment-card` in the `@foreach ($offerings as $offering)` loop:

### Dropdown trigger button

Replace:
```html
<form method="POST" action="/doctor/treatments/{{ $offering->id }}" onsubmit="return window.confirm('Eliminare questo trattamento?');">
  @csrf
  @method('DELETE')
  <button type="submit" class="dropdown-item text-danger">Elimina trattamento</button>
</form>
```

With:
```html
<button
  class="dropdown-item text-danger"
  type="button"
  data-bs-toggle="modal"
  data-bs-target="#deleteTreatmentModal{{ $offering->id }}"
>Elimina trattamento</button>
```

### Modal (inside `article.treatment-card`, after the card content)

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
```

## Modal ID Convention

`deleteTreatmentModal{{ $offering->id }}` — unique per card, same pattern as `appointmentCancelModal{{ $appointment->id }}`.

## Out of Scope

- No reason/motivo field (not needed for treatment deletion)
- No changes to `DoctorTreatmentController` or routes
- No Blade partial extraction (single use case, not worth the abstraction)
