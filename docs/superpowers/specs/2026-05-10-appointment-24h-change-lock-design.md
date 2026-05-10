# Appointment 24h change lock design

## Goal

Patients must not be able to cancel or reschedule a booked appointment during the 24 hours before the appointment start time.

## Scope

- Applies to patient-initiated cancellation.
- Applies to patient-initiated rescheduling.
- Does not change doctor-side status transitions or schedule-management cancellation flows.
- Does not change booking rules for new appointments.

## Backend behavior

The rule belongs in `App\Services\AppointmentService`, because both patient cancellation and rescheduling already pass through the service before modifying an appointment.

`validateFutureConfirmed()` will continue to enforce:

- only confirmed appointments can be changed;
- past appointments cannot be changed;
- appointments inside the 24-hour cutoff cannot be changed.

The cutoff is evaluated against the current application time:

```php
$appointment->start_at->lte(now()->addDay())
```

When the cutoff blocks the operation, the service throws a validation error. The message should be action-specific enough for the patient flow, for example:

- `Gli appuntamenti non possono essere annullati nelle 24 ore precedenti.`
- `Gli appuntamenti non possono essere spostati nelle 24 ore precedenti.`

## Patient UI behavior

The patient appointments page should not offer "Sposta appuntamento" or "Annulla appuntamento" when an upcoming appointment is inside the 24-hour cutoff. The card should instead show a short note that changes are unavailable in the 24 hours before the visit.

This UI change is only a usability improvement. The backend service remains the source of truth.

## Testing

Feature tests will cover:

- a patient cannot cancel a confirmed appointment starting within 24 hours;
- a patient cannot reschedule a confirmed appointment starting within 24 hours;
- the patient appointments page hides change actions and shows the cutoff note for appointments inside the cutoff;
- existing cancellation and rescheduling tests still pass for appointments outside the cutoff.

## Risks

The cutoff boundary must be explicit. This design blocks changes at exactly 24 hours before the appointment and any time after that.
