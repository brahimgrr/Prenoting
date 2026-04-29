# Booking Date Selection — No Reload + Arrow Navigation

## Goal

Remove full-page reloads from the date selection step of the booking wizard. Replace the "Settimana prima" / "Settimana dopo" text buttons with `‹` / `›` arrow buttons. Week navigation and day selection must both work without a page reload.

## Stack Constraints

HTML, Bootstrap 5, plain CSS, vanilla JS (browser built-ins only — no libraries, no frameworks, no Livewire).

## Architecture

### Day Selection (pre-render + show/hide)

The Blade template pre-renders the slot section for **all 5 weekdays** of the currently visible week. Each day's slot block carries a `data-date` attribute and is hidden by default (`d-none`). When the user clicks a day card, a JS handler removes `d-none` from that day's block and adds it to all others. No network request — instant display.

The week strip day cards get a `data-date` attribute (matching their slot block) instead of an `href`. JS handles the click. The URL is updated via `history.pushState` so the browser back button and manual refresh still restore state correctly.

### Week Navigation (fetch + partial swap)

The two arrows link to a new controller endpoint:

```
GET /patient/book/week?service_id=&week_start=&date=
```

This returns a Blade partial (`booking-week-partial.blade.php`) containing only the week strip + all five pre-rendered slot blocks. The arrows carry `data-week-url` attributes pointing to this endpoint. On click, JS calls `fetch(url)`, receives the HTML fragment, and swaps the `#booking-week-region` container in place. `history.pushState` updates the URL with the full booking page URL (not the partial URL).

### Arrow Buttons

The existing `<a>` week nav buttons are replaced with `<button>` elements styled with `btn btn-outline-secondary`. Text content becomes `‹` (prev) and `›` (next).

## Files Changed

| File | Change |
|---|---|
| `resources/views/patient/partials/booking-wizard.blade.php` | Pre-render all 5 days' slot blocks; add `data-date` on day cards; convert week nav to arrow buttons with `data-week-url`; wrap week strip + slot blocks in `#booking-week-region` |
| `resources/views/patient/partials/booking-week-partial.blade.php` | New partial — extracted week strip + slot blocks, used by both the wizard and the AJAX endpoint |
| `app/Http/Controllers/BookingController.php` | Add `week()` action returning the partial view |
| `routes/web.php` | Add `GET /patient/book/week` route pointing to `BookingController@week` |
| `resources/js/app.js` | Add `initBookingWizard()` — handles day click (show/hide) and week arrow click (fetch + swap) |
| `resources/css/app.css` | Minor `.week-nav` tweaks for arrow button sizing/alignment |

## Data Flow

### Initial page load
`BookingController::show()` loads slots for all 5 weekdays and passes them to the view. The wizard renders all slot blocks; only the selected day's block (if any) starts visible.

### Day click
1. JS reads `data-date` from clicked day card
2. Hides all `.slot-day-block` elements
3. Shows the `.slot-day-block[data-date="<clicked>"]`
4. Marks clicked day card as selected (`week-day--selected`)
5. `history.pushState` updates URL with `?date=<clicked>`

### Week arrow click
1. JS reads `data-week-url` from clicked arrow button
2. `fetch(url)` requests the partial endpoint
3. Response HTML replaces `#booking-week-region` innerHTML
4. JS re-initialises day-click handlers on new content
5. `history.pushState` updates full booking URL with new `week_start`

## Controller Changes

`BookingController::show()` gains a helper that loads slots for **all 5 weekdays** (not just `$selectedDate`). The existing `$slots` variable is replaced by `$weekSlots` — a `Collection` keyed by date string, each value being the slots for that day. The confirm step is unaffected as it uses `$selectedSlot`, not `$slots`.

The new `week()` action accepts `service_id`, `week_start`, and `date` query params, runs the same week-resolution logic, and returns `view('patient.partials.booking-week-partial', [...])`.

## Error Handling

If `fetch()` fails (network error or non-200 response), JS falls back to a standard `window.location.href` navigation using the same URL — the user gets a page reload rather than a broken UI.

## No Scope Creep

- Month jump select keeps its existing JS (`initMonthJumpSelects`) — it still navigates on change (full reload acceptable for month jumps).
- Service selection (step 1) and slot/confirm steps are not touched.
- No slot pre-loading across multiple weeks — only the visible week is pre-rendered.
