# Project Deep Dive

This Laravel/Blade application manages patient registration, patient booking, doctor agenda, treatments, and profile data for a single-office medical practice.

## Current Domain Shape

- The app currently supports one active doctor profile and one office.
- Availability is doctor-owned, but there is no office/location table.
- The app does not persist future appointment slots. It stores schedule rules and generates bookable starts dynamically.
- Appointments are the historical source of booked time. Each appointment stores `doctor_profile_id`, `patient_id`, `service_id`, `start_at`, `end_at`, `status`, notes, and cancellation reason.

## Availability Tables

`working_hours`

- Recurring weekly opening windows owned by `doctor_profiles`.
- Important fields: `weekday`, `start_time`, `end_time`, optional `effective_from`, optional `effective_until`, `is_active`.
- Lunch breaks are represented as two working-hour windows, for example `09:00-13:00` and `14:00-18:00`.

`special_openings`

- One-off extra opening windows for a specific date.
- Used for days or hours outside the ordinary weekly schedule.

`closures`

- One-off closures for a specific date.
- Nullable `start_time` and `end_time` means the whole day is closed.
- Partial closures block only the overlapping interval.
- Closures have precedence over both working hours and special openings.

## Virtual Slots

`App\Services\AvailabilityService` generates virtual slots at request time:

1. Load working-hour windows for the doctor and selected date.
2. Add special openings for that date.
3. Apply closures as exclusions.
4. Split remaining windows on a fixed 30-minute start grid.
5. Use `medical_services.duration_minutes` to ensure the full appointment fits in a window.
6. Exclude starts that overlap active appointments (`confirmed`, `checked_in`).

The generated object is `App\Support\VirtualAvailabilitySlot` with:

- `key`: URL/form-safe start key, formatted as `YYYY-MM-DDTHH:mm`.
- `start_at`
- `end_at`
- `doctor_profile_id`

## Booking Flow

The patient booking wizard submits:

```php
'slot_start' => '2030-05-02T09:00',
'service_id' => 3,
```

`BookingController@store` validates `slot_start`, `service_id`, and notes, then calls `AppointmentService::book`.

`AppointmentService::book` runs inside a database transaction:

1. Lock the active doctor profile row with `lockForUpdate()`.
2. Load the requested service.
3. Ask `AvailabilityService` to regenerate and validate the requested virtual slot.
4. Create the appointment with copied `start_at` and `end_at`.

The doctor row lock serializes competing bookings against the same single-doctor calendar.

## Rescheduling And Cancellation

- Rescheduling uses the same `slot_start` validation as booking.
- The current appointment is excluded from overlap checks while finding replacement times.
- Cancellation only changes appointment state and cancellation reason.
- A cancelled time becomes available again if it is still inside an opening rule and not blocked by a closure.

## Doctor Agenda

The agenda combines four sources:

- Generated free starts from `AvailabilityService::agendaSlotsForDate`.
- Active and historical appointments from `appointments`.
- Closure intervals from `closures`.
- One-off extra opening events from `special_openings`.

The doctor can block a generated free start. Blocking creates a partial closure for that 30-minute interval. Reopening deletes that closure.

Recurring availability is no longer configured from Agenda. Agenda is reserved for non-recurring calendar events: full-day or partial closures, vacation ranges, and one-off special openings.

## Doctor Profile Working Hours

The doctor profile owns the permanent weekly template. The "Orari ambulatorio" form edits all weekdays in one save. Saving replaces the active `working_hours` rows for the doctor with forever-valid rows (`effective_from` and `effective_until` are null). Existing active appointments protect the template: a save is rejected if a future appointment would no longer be covered by the proposed weekly windows or an existing special opening.

## API

`GET /availability`

- Optional `service` filters the generated availability by service duration.
- Optional `date` limits generation to one day.
- Without `date`, the endpoint returns future generated starts within the booking horizon.

Response items include:

```json
{
  "slot_key": "2030-05-02T09:00",
  "slot_start": "2030-05-02T09:00",
  "start_at": "2030-05-02T07:00:00.000000Z",
  "end_at": "2030-05-02T07:30:00.000000Z"
}
```

## Seed Data

`database/seeders/DatabaseSeeder.php` creates:

- admin, patient, and doctor users;
- the doctor profile;
- active medical services;
- weekday morning working hours for the doctor.

## Testing Notes

Use the containerized PHP runtime for the full suite:

```bash
docker compose exec app php vendor/bin/phpunit --colors=never
```

The local Windows PHP runtime used during this change did not include SQLite/PDO database drivers, so local `composer test` cannot run database-backed feature tests unless those extensions are installed.
