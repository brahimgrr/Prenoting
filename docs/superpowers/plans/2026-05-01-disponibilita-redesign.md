# Disponibilità Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ridisegnare la sezione Disponibilità del portale medico con layout a modale, tab bar giorni, slot panel e supporto pausa pranzo.

**Architecture:** La view viene riscritta completamente con un header + pulsante che apre una modale. La modale ha due schermate (form / preview) gestite via JS; la preview usa il page-reload esistente (GET preview → POST batch). La pausa pranzo esclude gli slot nell'intervallo indicato durante la generazione del batch. Il controller aggiunge la validazione opzionale dei campi pausa e la relativa logica di esclusione nel preview builder.

**Tech Stack:** Laravel Blade, vanilla JS, CSS custom (no framework aggiuntivo), PHPUnit Feature tests.

---

## File Map

| File | Azione |
|------|--------|
| `app/Http/Controllers/DoctorDashboardController.php` | Modifica: lunch break validation + skip logic + eager load |
| `resources/css/app.css` | Modifica: aggiunta classi `.avail-*` |
| `resources/views/doctor/availability.blade.php` | Riscrittura completa |
| `tests/Feature/RoleDashboardTest.php` | Modifica: aggiorna assertions + aggiunge test lunch break |

---

## Task 1: Controller — pausa pranzo + eager load

**Files:**
- Modify: `app/Http/Controllers/DoctorDashboardController.php`

- [ ] **Step 1: Aggiungere validazione pausa pranzo in `validatedBatchAvailability()`**

Sostituire il metodo con:

```php
private function validatedBatchAvailability(Request $request): array
{
  $validated = $request->validate([
    'start_date'          => ['required', 'date_format:Y-m-d'],
    'end_date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
    'weekdays'            => ['required', 'array', 'min:1'],
    'weekdays.*'          => ['integer', 'between:1,5'],
    'start_time'          => ['required', 'date_format:H:i'],
    'end_time'            => ['required', 'date_format:H:i'],
    'slot_duration'       => ['required', 'integer', 'in:15,20,30,45,60'],
    'lunch_break_enabled' => ['nullable', 'string'],
    'lunch_break_start'   => ['nullable', 'required_if:lunch_break_enabled,1', 'date_format:H:i'],
    'lunch_break_end'     => ['nullable', 'required_if:lunch_break_enabled,1', 'date_format:H:i'],
  ]);

  $startTime = CarbonImmutable::parse("2000-01-01 {$validated['start_time']}:00");
  $endTime   = CarbonImmutable::parse("2000-01-01 {$validated['end_time']}:00");
  if ($endTime->lessThanOrEqualTo($startTime)) {
    throw ValidationException::withMessages([
      'end_time' => "L'orario di fine deve essere successivo all'inizio.",
    ]);
  }

  $validated['lunch_break_enabled'] = ($validated['lunch_break_enabled'] ?? null) === '1';
  $validated['weekdays'] = collect($validated['weekdays'])
    ->map(fn ($w) => (int) $w)
    ->unique()->sort()->values()->all();
  $validated['slot_duration'] = (int) $validated['slot_duration'];

  return $validated;
}
```

- [ ] **Step 2: Aggiungere skip pausa in `buildAvailabilityPreview()`**

Aggiungere, subito dopo `[$startHour, $startMinute]` e `[$endHour, $endMinute]`:

```php
$lunchBreakEnabled = $input['lunch_break_enabled'] ?? false;
$lunchStart = $lunchBreakEnabled && isset($input['lunch_break_start'])
  ? CarbonImmutable::parse("2000-01-01 {$input['lunch_break_start']}:00")
  : null;
$lunchEnd = $lunchBreakEnabled && isset($input['lunch_break_end'])
  ? CarbonImmutable::parse("2000-01-01 {$input['lunch_break_end']}:00")
  : null;
```

Poi, nell'inner loop — prima dell'`if ($slotStart->isPast())` — aggiungere:

```php
if ($lunchStart && $lunchEnd) {
  $slotStartTime = CarbonImmutable::parse("2000-01-01 {$slotStart->format('H:i')}:00");
  $slotEndTime   = CarbonImmutable::parse("2000-01-01 {$slotEnd->format('H:i')}:00");
  if ($slotStartTime->lessThan($lunchEnd) && $slotEndTime->greaterThan($lunchStart)) {
    $skipped->push($candidate + ['reason' => 'pausa pranzo']);
    continue;
  }
}
```

- [ ] **Step 3: Aggiungere defaults pausa in `viewAvailability()` ed eager load appointment**

Sostituire il metodo `viewAvailability`:

```php
private function viewAvailability(Request $request, ?array $availabilityPreview = null): View
{
  $batchForm = $availabilityPreview['input'] ?? [
    'start_date'          => CarbonImmutable::now()->toDateString(),
    'end_date'            => CarbonImmutable::now()->addWeeks(2)->toDateString(),
    'weekdays'            => [1, 2, 3, 4, 5],
    'start_time'          => '09:00',
    'end_time'            => '12:00',
    'slot_duration'       => 30,
    'lunch_break_enabled' => false,
    'lunch_break_start'   => '13:00',
    'lunch_break_end'     => '14:00',
  ];

  return view('doctor.availability', [
    'availabilityPreview' => $availabilityPreview,
    'batchForm'           => $batchForm,
    'availabilitySlots'   => AvailabilitySlot::query()
      ->where('start_at', '>=', now())
      ->with(['appointments.patient.user'])
      ->orderBy('start_at')
      ->get()
      ->groupBy(fn (AvailabilitySlot $slot) => $slot->start_at->toDateString()),
  ]);
}
```

- [ ] **Step 4: Verificare che i test esistenti passino ancora**

```bash
php artisan test --filter=RoleDashboardTest
```

I test che controllano `class="availability-manager"` e `Crea disponibilita in batch` falliranno — è previsto, li aggiorniamo nel Task 4.

---

## Task 2: CSS — classi `.avail-*`

**Files:**
- Modify: `resources/css/app.css` (aggiungere in fondo)

- [ ] **Step 1: Aggiungere le classi in fondo al file `app.css`**

```css
/* ── Disponibilità redesign ── */
.avail-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.avail-page-title { font-size: 22px; font-weight: 700; margin: 0; }
.avail-page-subtitle { font-size: 13px; color: #8a9aae; margin: 3px 0 0; }

.avail-btn-crea { display: inline-flex; align-items: center; gap: 7px; background: #1a6fce; color: #fff; border: none; border-radius: 9px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.avail-btn-crea:hover { background: #155bb5; }

.avail-summary-strip { display: flex; gap: 12px; margin-bottom: 20px; }
.avail-summary-card { flex: 1; background: #fff; border-radius: 10px; padding: 14px 16px; border: 1px solid #e8eaed; }
.avail-summary-val { font-size: 26px; font-weight: 800; line-height: 1; }
.avail-summary-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #8a9aae; margin-top: 4px; }

.avail-tabs-wrap { background: #fff; border-radius: 11px 11px 0 0; border: 1px solid #e8eaed; border-bottom: none; display: flex; overflow-x: auto; }
.avail-day-tab { flex: 1; min-width: 90px; padding: 12px 10px 10px; cursor: pointer; border-bottom: 2px solid transparent; text-align: center; }
.avail-day-tab.is-active { border-bottom-color: #1a6fce; background: #f0f4ff; }
.avail-day-tab__dow { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #8a9aae; }
.avail-day-tab__date { font-size: 13px; font-weight: 700; color: #0f1724; margin-top: 1px; }
.avail-day-tab.is-active .avail-day-tab__date { color: #1a6fce; }
.avail-day-tab__dots { display: flex; gap: 4px; justify-content: center; margin-top: 5px; }
.avail-dot { width: 6px; height: 6px; border-radius: 50%; }

.avail-slot-panel { background: #fff; border-radius: 0 0 11px 11px; border: 1px solid #e8eaed; border-top: none; padding: 16px; }
.avail-slot-panel__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f0f2f5; }
.avail-slot-panel__title { font-size: 13px; font-weight: 600; color: #4a5568; }
.avail-slot-count { font-size: 11px; font-weight: 600; color: #8a9aae; background: #f0f2f5; border-radius: 20px; padding: 2px 10px; }
.avail-slot-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; margin-bottom: 6px; border: 1px solid #e8eaed; background: #f8f9fb; }
.avail-slot-row:last-child { margin-bottom: 0; }
.avail-slot-bar { width: 3px; height: 30px; border-radius: 2px; flex-shrink: 0; }
.avail-slot-time { font-size: 14px; font-weight: 700; color: #0f1724; flex: 1; }
.avail-slot-patient { font-size: 12px; color: #8a9aae; }
.avail-slot-badge { font-size: 11px; font-weight: 600; border-radius: 5px; padding: 3px 9px; }
.avail-badge--free { color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; }
.avail-badge--booked { color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; }
.avail-badge--blocked { color: #d97706; background: #fffbeb; border: 1px solid #fde68a; }
.avail-slot-btn { font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 12px; cursor: pointer; white-space: nowrap; }

.avail-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,36,.45); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.avail-modal-box { background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,.2); width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
.avail-modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #e8eaed; display: flex; justify-content: space-between; align-items: flex-start; }
.avail-modal-title { font-size: 16px; font-weight: 700; color: #0f1724; margin: 0; }
.avail-modal-subtitle { font-size: 13px; color: #8a9aae; margin: 3px 0 0; }
.avail-modal-close { background: none; border: none; color: #8a9aae; font-size: 20px; cursor: pointer; line-height: 1; padding: 0; }
.avail-modal-close:hover { color: #0f1724; }
.avail-modal-body { padding: 20px 24px; }
.avail-modal-footer { padding: 14px 24px; border-top: 1px solid #e8eaed; display: flex; justify-content: flex-end; gap: 8px; background: #f8f9fa; border-radius: 0 0 14px 14px; }

.avail-field-lbl { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #6b7a8d; margin-bottom: 6px; }
.avail-field-row { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-end; }
.avail-field-col { flex: 1; }
.avail-field-sep { color: #8a9aae; font-size: 13px; padding-bottom: 10px; }
.avail-input { width: 100%; padding: 9px 12px; border: 1.5px solid #dde1e7; border-radius: 8px; font-size: 14px; color: #1a1f2e; background: #fff; outline: none; font-family: inherit; }
.avail-input:focus { border-color: #1a6fce; box-shadow: 0 0 0 3px rgba(26,111,206,.1); }

.avail-chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.avail-chip { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1.5px solid #dde1e7; background: #fff; color: #4a5568; user-select: none; }
.avail-chip.is-selected { background: #1a6fce; border-color: #1a6fce; color: #fff; }

.avail-pausa { border: 1.5px solid #e8eaed; border-radius: 9px; overflow: hidden; margin-bottom: 16px; }
.avail-pausa.is-on { border-color: #f59e0b; }
.avail-pausa__toggle { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; cursor: pointer; background: #fefce8; }
.avail-pausa__label { font-size: 13px; font-weight: 600; color: #92400e; }
.avail-pausa__switch { width: 36px; height: 20px; border-radius: 10px; background: #e8eaed; position: relative; flex-shrink: 0; transition: background .15s; }
.avail-pausa.is-on .avail-pausa__switch { background: #f59e0b; }
.avail-pausa__switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: transform .15s; }
.avail-pausa.is-on .avail-pausa__switch::after { transform: translateX(16px); }
.avail-pausa__fields { padding: 12px 14px; display: flex; gap: 10px; background: #fffbeb; border-top: 1px solid #fde68a; }
.avail-pausa__hint { font-size: 11px; color: #a16207; padding: 0 14px 10px; background: #fffbeb; }

.avail-preview-bar { background: #f4f6f9; border-radius: 8px; padding: 11px 14px; display: flex; align-items: center; gap: 10px; margin-top: 16px; }
.avail-preview-count { background: #1a6fce; color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 700; }
.avail-empty { color: #8a9aae; font-size: 13px; text-align: center; padding: 24px 0; margin: 0; }
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: build completato senza errori.

---

## Task 3: Blade — riscrittura completa

**Files:**
- Modify: `resources/views/doctor/availability.blade.php`

- [ ] **Step 1: Sostituire l'intero contenuto del file**

```blade
@extends('layouts.portal', ['title' => 'Disponibilita - MedPortal'])

@php
  $availabilityTotals = $availabilitySlots->flatten(1);
  $totalFree    = $availabilityTotals->filter(fn ($s) => !$s->is_booked && !$s->is_blocked)->count();
  $totalBooked  = $availabilityTotals->where('is_booked', true)->count();
  $totalCount   = $availabilityTotals->count();

  $weekdayOptions   = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN'];
  $selectedWeekdays = old('weekdays', $batchForm['weekdays'] ?? [1,2,3,4,5]);
  $selectedWeekdays = is_array($selectedWeekdays) ? array_map('intval', $selectedWeekdays) : [1,2,3,4,5];

  $lunchEnabled = old('lunch_break_enabled', $batchForm['lunch_break_enabled'] ?? false);
  $lunchStart   = old('lunch_break_start', $batchForm['lunch_break_start'] ?? '13:00');
  $lunchEnd     = old('lunch_break_end',   $batchForm['lunch_break_end']   ?? '14:00');

  $dow = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Gio','Fri'=>'Ven','Sat'=>'Sab','Sun'=>'Dom'];
@endphp

@section('content')
<section class="portal-section">

  {{-- ── Header ── --}}
  <div class="avail-page-header">
    <div>
      <h1 class="avail-page-title">Gestisci disponibilita</h1>
      <p class="avail-page-subtitle">Crea slot in batch e controlla la disponibilita futura</p>
    </div>
    <button class="avail-btn-crea" onclick="availOpenModal()">
      + Crea disponibilita
    </button>
  </div>

  {{-- ── Summary strip ── --}}
  <div class="avail-summary-strip">
    <div class="avail-summary-card">
      <div class="avail-summary-val" style="color:#0f1724">{{ $totalCount }}</div>
      <div class="avail-summary-lbl">Slot totali</div>
    </div>
    <div class="avail-summary-card">
      <div class="avail-summary-val" style="color:#16a34a">{{ $totalFree }}</div>
      <div class="avail-summary-lbl">Liberi</div>
    </div>
    <div class="avail-summary-card">
      <div class="avail-summary-val" style="color:#2563eb">{{ $totalBooked }}</div>
      <div class="avail-summary-lbl">Prenotati</div>
    </div>
  </div>

  {{-- ── Tab bar + slot panels ── --}}
  @if ($availabilitySlots->isNotEmpty())
    <div class="avail-tabs-wrap" id="availTabBar">
      @foreach ($availabilitySlots as $slotDate => $slotsForDay)
        @php
          $carbon   = \Carbon\CarbonImmutable::parse($slotDate);
          $dowLabel = $dow[$carbon->format('D')] ?? $carbon->format('D');
          $hasFree    = $slotsForDay->filter(fn($s) => !$s->is_booked && !$s->is_blocked)->isNotEmpty();
          $hasBooked  = $slotsForDay->where('is_booked', true)->isNotEmpty();
          $hasBlocked = $slotsForDay->where('is_blocked', true)->isNotEmpty();
          $tabIdx = $loop->index;
        @endphp
        <div class="avail-day-tab{{ $loop->first ? ' is-active' : '' }}" onclick="availSwitchDay({{ $tabIdx }})">
          <div class="avail-day-tab__dow">{{ $dowLabel }}</div>
          <div class="avail-day-tab__date">{{ $carbon->format('d/m') }}</div>
          <div class="avail-day-tab__dots">
            @if ($hasFree)    <span class="avail-dot" style="background:#16a34a"></span> @endif
            @if ($hasBooked)  <span class="avail-dot" style="background:#2563eb"></span> @endif
            @if ($hasBlocked) <span class="avail-dot" style="background:#d97706"></span> @endif
          </div>
        </div>
      @endforeach
    </div>

    @foreach ($availabilitySlots as $slotDate => $slotsForDay)
      @php $carbon = \Carbon\CarbonImmutable::parse($slotDate); @endphp
      <div class="avail-day-panel" id="avail-panel-{{ $loop->index }}" @if (!$loop->first) style="display:none" @endif>
        <div class="avail-slot-panel">
          <div class="avail-slot-panel__head">
            <span class="avail-slot-panel__title">{{ $carbon->format('d/m/Y') }}</span>
            <span class="avail-slot-count">{{ $slotsForDay->count() }} slot</span>
          </div>

          @forelse ($slotsForDay as $slot)
            @php
              $isFree    = !$slot->is_booked && !$slot->is_blocked;
              $isBooked  = $slot->is_booked;
              $isBlocked = $slot->is_blocked;
              $barColor  = $isFree ? '#16a34a' : ($isBooked ? '#2563eb' : '#d97706');
              $patientName = $isBooked ? ($slot->appointments->first()?->patientName() ?? null) : null;
            @endphp
            <div class="avail-slot-row">
              <div class="avail-slot-bar" style="background:{{ $barColor }}"></div>
              <span class="avail-slot-time">{{ $slot->start_at->format('H:i') }} – {{ $slot->end_at->format('H:i') }}</span>
              @if ($patientName)
                <span class="avail-slot-patient">{{ $patientName }}</span>
              @endif
              @if ($isFree)
                <span class="avail-slot-badge avail-badge--free">Libero</span>
                <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                  @csrf
                  <button type="submit" class="avail-slot-btn">Blocca</button>
                </form>
              @elseif ($isBooked)
                <span class="avail-slot-badge avail-badge--booked">Prenotato</span>
              @else
                <span class="avail-slot-badge avail-badge--blocked">Bloccato</span>
                <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                  @csrf
                  <button type="submit" class="avail-slot-btn">Riapri</button>
                </form>
              @endif
            </div>
          @empty
            <p class="avail-empty">Nessuno slot per questo giorno.</p>
          @endforelse
        </div>
      </div>
    @endforeach
  @else
    <div class="avail-slot-panel" style="border-radius:11px">
      <p class="avail-empty">Nessuna disponibilita futura. Crea nuovi slot con il pulsante in alto.</p>
    </div>
  @endif

</section>

{{-- ════ MODALE ════ --}}
<div class="avail-modal-overlay" id="availModal" style="display:none" onclick="if(event.target===this)availCloseModal()">
  <div class="avail-modal-box">

    {{-- Header modale --}}
    <div class="avail-modal-header">
      <div>
        <p class="avail-modal-title" id="availModalTitle">Crea disponibilita</p>
        <p class="avail-modal-subtitle" id="availModalSubtitle">Genera slot in batch per un periodo selezionato</p>
      </div>
      <button class="avail-modal-close" onclick="availCloseModal()">&#x2715;</button>
    </div>

    {{-- ── Schermata 1: Form ── --}}
    <div id="availFormScreen">
      <form method="GET" action="/doctor/availability/preview" id="availPreviewForm">
        <div class="avail-modal-body">

          {{-- Date range --}}
          <div class="avail-field-row">
            <div class="avail-field-col">
              <div class="avail-field-lbl">Dal</div>
              <input type="date" class="avail-input" name="start_date"
                value="{{ old('start_date', $batchForm['start_date']) }}" required>
            </div>
            <div class="avail-field-sep">→</div>
            <div class="avail-field-col">
              <div class="avail-field-lbl">Al</div>
              <input type="date" class="avail-input" name="end_date"
                value="{{ old('end_date', $batchForm['end_date']) }}" required>
            </div>
          </div>

          {{-- Time range --}}
          <div class="avail-field-row">
            <div class="avail-field-col">
              <div class="avail-field-lbl">Ora inizio</div>
              <input type="time" class="avail-input" name="start_time"
                value="{{ old('start_time', $batchForm['start_time']) }}" required>
            </div>
            <div class="avail-field-sep">—</div>
            <div class="avail-field-col">
              <div class="avail-field-lbl">Ora fine</div>
              <input type="time" class="avail-input" name="end_time"
                value="{{ old('end_time', $batchForm['end_time']) }}" required>
            </div>
          </div>

          {{-- Duration hidden --}}
          <input type="hidden" name="slot_duration" value="30">

          {{-- Giorni chips --}}
          <div style="margin-bottom:16px">
            <div class="avail-field-lbl">Giorni</div>
            <div class="avail-chip-row">
              @foreach ($weekdayOptions as $val => $label)
                <label>
                  <input type="checkbox" name="weekdays[]" value="{{ $val }}"
                    style="display:none"
                    @checked(in_array($val, $selectedWeekdays, true))
                    onchange="availSyncChip(this)">
                  <span class="avail-chip{{ in_array($val, $selectedWeekdays, true) ? ' is-selected' : '' }}"
                    onclick="this.previousElementSibling.click()">{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          {{-- Pausa pranzo --}}
          <div class="avail-pausa{{ $lunchEnabled ? ' is-on' : '' }}" id="availPausaBox">
            <div class="avail-pausa__toggle" onclick="availTogglePausa()">
              <span class="avail-pausa__label">&#9749; Pausa pranzo</span>
              <div class="avail-pausa__switch"></div>
            </div>
            <input type="hidden" name="lunch_break_enabled" id="availLunchEnabled"
              value="{{ $lunchEnabled ? '1' : '' }}">
            <div class="avail-pausa__fields" id="availPausaFields"
              @if (!$lunchEnabled) style="display:none" @endif>
              <div style="flex:1">
                <div class="avail-field-lbl" style="color:#a16207">Pausa inizio</div>
                <input type="time" class="avail-input" name="lunch_break_start"
                  value="{{ old('lunch_break_start', $lunchStart) }}">
              </div>
              <div class="avail-field-sep">—</div>
              <div style="flex:1">
                <div class="avail-field-lbl" style="color:#a16207">Pausa fine</div>
                <input type="time" class="avail-input" name="lunch_break_end"
                  value="{{ old('lunch_break_end', $lunchEnd) }}">
              </div>
            </div>
            <div class="avail-pausa__hint" id="availPausaHint"
              @if (!$lunchEnabled) style="display:none" @endif>
              Gli slot in questo intervallo verranno esclusi automaticamente.
            </div>
          </div>

        </div>
        <div class="avail-modal-footer">
          <button type="button" onclick="availCloseModal()"
            style="background:none;border:1.5px solid #dde1e7;border-radius:8px;padding:8px 18px;font-size:14px;font-weight:500;color:#4a5568;cursor:pointer">
            Annulla
          </button>
          <button type="submit"
            style="background:#1a6fce;border:none;border-radius:8px;padding:8px 22px;font-size:14px;font-weight:600;color:#fff;cursor:pointer">
            Genera anteprima
          </button>
        </div>
      </form>
    </div>

    {{-- ── Schermata 2: Preview ── --}}
    @if ($availabilityPreview)
    <div id="availPreviewScreen">
      <div class="avail-modal-body">
        <div class="avail-preview-bar">
          <span class="avail-preview-count">{{ $availabilityPreview['creatable']->count() }}</span>
          <span style="font-size:13px;color:#4a5568">
            slot verranno creati · solo futuri, senza sovrapposizioni
          </span>
        </div>
        @if ($availabilityPreview['skipped']->isNotEmpty())
          <p style="font-size:12px;color:#8a9aae;margin-top:10px;margin-bottom:0">
            {{ $availabilityPreview['skipped']->count() }} slot saltati
            (passati, sovrapposti o in pausa pranzo)
          </p>
        @endif
      </div>
      <div class="avail-modal-footer">
        <button type="button" onclick="availShowFormScreen()"
          style="background:none;border:1.5px solid #dde1e7;border-radius:8px;padding:8px 18px;font-size:14px;font-weight:500;color:#4a5568;cursor:pointer">
          ← Modifica
        </button>
        <form method="POST" action="/doctor/availability/batch">
          @csrf
          <input type="hidden" name="start_date"          value="{{ $availabilityPreview['input']['start_date'] }}">
          <input type="hidden" name="end_date"            value="{{ $availabilityPreview['input']['end_date'] }}">
          <input type="hidden" name="start_time"          value="{{ $availabilityPreview['input']['start_time'] }}">
          <input type="hidden" name="end_time"            value="{{ $availabilityPreview['input']['end_time'] }}">
          <input type="hidden" name="slot_duration"       value="{{ $availabilityPreview['input']['slot_duration'] }}">
          @if ($availabilityPreview['input']['lunch_break_enabled'])
            <input type="hidden" name="lunch_break_enabled" value="1">
            <input type="hidden" name="lunch_break_start"   value="{{ $availabilityPreview['input']['lunch_break_start'] }}">
            <input type="hidden" name="lunch_break_end"     value="{{ $availabilityPreview['input']['lunch_break_end'] }}">
          @endif
          @foreach ($availabilityPreview['input']['weekdays'] as $wd)
            <input type="hidden" name="weekdays[]" value="{{ $wd }}">
          @endforeach
          <button type="submit"
            @disabled($availabilityPreview['creatable']->isEmpty())
            style="background:#1a6fce;border:none;border-radius:8px;padding:8px 22px;font-size:14px;font-weight:600;color:#fff;cursor:pointer">
            Crea {{ $availabilityPreview['creatable']->count() }} slot
          </button>
        </form>
      </div>
    </div>
    @endif

  </div>
</div>

{{-- ── JS ── --}}
<script>
function availOpenModal() {
  document.getElementById('availModal').style.display = 'flex';
  availShowFormScreen();
}
function availCloseModal() {
  document.getElementById('availModal').style.display = 'none';
}
function availShowFormScreen() {
  document.getElementById('availFormScreen').style.display = '';
  const ps = document.getElementById('availPreviewScreen');
  if (ps) ps.style.display = 'none';
  document.getElementById('availModalTitle').textContent = 'Crea disponibilita';
  document.getElementById('availModalSubtitle').textContent = 'Genera slot in batch per un periodo selezionato';
}
function availShowPreviewScreen() {
  document.getElementById('availFormScreen').style.display = 'none';
  const ps = document.getElementById('availPreviewScreen');
  if (ps) ps.style.display = '';
  document.getElementById('availModalTitle').textContent = 'Anteprima slot';
  document.getElementById('availModalSubtitle').textContent = 'Controlla prima di creare';
}
function availSwitchDay(idx) {
  document.querySelectorAll('.avail-day-tab').forEach((t, i) => {
    t.classList.toggle('is-active', i === idx);
  });
  document.querySelectorAll('.avail-day-panel').forEach((p, i) => {
    p.style.display = i === idx ? '' : 'none';
  });
}
function availSyncChip(input) {
  input.nextElementSibling.classList.toggle('is-selected', input.checked);
}
function availTogglePausa() {
  const box    = document.getElementById('availPausaBox');
  const fields = document.getElementById('availPausaFields');
  const hint   = document.getElementById('availPausaHint');
  const input  = document.getElementById('availLunchEnabled');
  const isOn   = box.classList.toggle('is-on');
  fields.style.display = isOn ? 'flex' : 'none';
  hint.style.display   = isOn ? 'block' : 'none';
  input.value          = isOn ? '1' : '';
}
@if ($availabilityPreview)
window.addEventListener('DOMContentLoaded', function () {
  document.getElementById('availModal').style.display = 'flex';
  availShowPreviewScreen();
});
@endif
</script>
@endsection
```

- [ ] **Step 2: Verificare la build**

```bash
npm run build
```

Expected: build completato senza errori.

---

## Task 4: Test — aggiornamento assertions + nuovo test pausa pranzo

**Files:**
- Modify: `tests/Feature/RoleDashboardTest.php`

- [ ] **Step 1: Aggiornare `test_doctor_availability_is_a_dedicated_section`**

Trovare il test e sostituire le assertions che usano le vecchie classi:

```php
public function test_doctor_availability_is_a_dedicated_section(): void
{
    $doctorUser = $this->makeDoctorUser();

    $this->actingAs($doctorUser)
        ->get('/doctor/availability')
        ->assertOk()
        ->assertSee('Gestisci disponibilita')
        ->assertSee('Crea disponibilita')
        ->assertSee('/doctor/availability/preview', false);
}
```

- [ ] **Step 2: Aggiornare `test_doctor_can_see_availability_slots` (o equivalente che controlla le classi vecchie)**

Trovare qualsiasi assertion che usa `class="availability-manager"` o `class="availability-day"` e rimuoverle o aggiornare con le nuove classi:

```php
->assertSee('avail-slot-row', false)
->assertSee('avail-day-tab', false)
```

- [ ] **Step 3: Aggiungere test pausa pranzo**

Aggiungere questo test nella classe:

```php
public function test_doctor_preview_excludes_lunch_break_slots(): void
{
    $doctorUser = $this->makeDoctorUser();
    $startDate  = now()->addDay()->toDateString();

    $response = $this->actingAs($doctorUser)->get('/doctor/availability/preview?' . http_build_query([
        'start_date'          => $startDate,
        'end_date'            => $startDate,
        'weekdays'            => [now()->addDay()->dayOfWeekIso],
        'start_time'          => '09:00',
        'end_time'            => '14:00',
        'slot_duration'       => '30',
        'lunch_break_enabled' => '1',
        'lunch_break_start'   => '13:00',
        'lunch_break_end'     => '14:00',
    ]));

    $response->assertOk();

    // 09:00-13:00 = 8 slot da 30 min; 13:00-14:00 esclusi = 0 slot in pausa
    $view = $response->viewData('availabilityPreview');
    $creatableTimes = $view['creatable']->map(fn ($c) => $c['start_at']->format('H:i'))->all();

    $this->assertNotContains('13:00', $creatableTimes);
    $this->assertContains('09:00', $creatableTimes);
    $this->assertContains('12:30', $creatableTimes);

    $skippedReasons = $view['skipped']->pluck('reason')->all();
    $this->assertContains('pausa pranzo', $skippedReasons);
}
```

- [ ] **Step 4: Eseguire tutti i test**

```bash
php artisan test --filter=RoleDashboardTest
```

Expected: tutti i test passano.

- [ ] **Step 5: Commit finale**

```bash
git add resources/views/doctor/availability.blade.php \
        resources/css/app.css \
        app/Http/Controllers/DoctorDashboardController.php \
        tests/Feature/RoleDashboardTest.php
git commit -m "feat: redesign disponibilita section with modal, day tabs and lunch break support"
```
