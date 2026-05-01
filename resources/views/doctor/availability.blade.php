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
              $patientName = $isBooked ? $slot->appointments->first()?->patientName() : null;
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
                  value="{{ $lunchStart }}">
              </div>
              <div class="avail-field-sep">—</div>
              <div style="flex:1">
                <div class="avail-field-lbl" style="color:#a16207">Pausa fine</div>
                <input type="time" class="avail-input" name="lunch_break_end"
                  value="{{ $lunchEnd }}">
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
            {{ $availabilityPreview['skipped']->count() }} {{ $availabilityPreview['skipped']->count() === 1 ? 'slot saltato' : 'slot saltati' }}
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
  var ps = document.getElementById('availPreviewScreen');
  if (ps) ps.style.display = 'none';
  document.getElementById('availModalTitle').textContent = 'Crea disponibilita';
  document.getElementById('availModalSubtitle').textContent = 'Genera slot in batch per un periodo selezionato';
}
function availShowPreviewScreen() {
  document.getElementById('availFormScreen').style.display = 'none';
  var ps = document.getElementById('availPreviewScreen');
  if (ps) ps.style.display = '';
  document.getElementById('availModalTitle').textContent = 'Anteprima slot';
  document.getElementById('availModalSubtitle').textContent = 'Controlla prima di creare';
}
function availSwitchDay(idx) {
  document.querySelectorAll('.avail-day-tab').forEach(function(t, i) {
    t.classList.toggle('is-active', i === idx);
  });
  document.querySelectorAll('.avail-day-panel').forEach(function(p, i) {
    p.style.display = i === idx ? '' : 'none';
  });
}
function availSyncChip(input) {
  input.nextElementSibling.classList.toggle('is-selected', input.checked);
}
function availTogglePausa() {
  var box    = document.getElementById('availPausaBox');
  var fields = document.getElementById('availPausaFields');
  var hint   = document.getElementById('availPausaHint');
  var input  = document.getElementById('availLunchEnabled');
  var isOn   = box.classList.toggle('is-on');
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
