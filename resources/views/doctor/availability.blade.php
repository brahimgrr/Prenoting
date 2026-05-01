@extends('layouts.portal', ['title' => 'Disponibilita - MedPortal'])

@php
  $availabilityTotals = $availabilitySlots->flatten(1);
  $totalFree = $availabilityTotals->filter(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked)->count();
  $totalBooked = $availabilityTotals->where('is_booked', true)->count();
  $totalBlocked = $availabilityTotals->where('is_blocked', true)->count();
  $totalCount = $availabilityTotals->count();

  $weekdayOptions = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN'];
  $selectedWeekdays = old('weekdays', $batchForm['weekdays'] ?? [1, 2, 3, 4, 5]);
  $selectedWeekdays = is_array($selectedWeekdays) ? array_map('intval', $selectedWeekdays) : [1, 2, 3, 4, 5];

  $lunchEnabled = old('lunch_break_enabled', $batchForm['lunch_break_enabled'] ?? false);
  $lunchStart = old('lunch_break_start', $batchForm['lunch_break_start'] ?? '13:00');
  $lunchEnd = old('lunch_break_end', $batchForm['lunch_break_end'] ?? '14:00');

  $weekdayLabels = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Gio', 'Fri' => 'Ven', 'Sat' => 'Sab', 'Sun' => 'Dom'];
@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Disponibilita</h1>
      </div>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#availabilityBatchModal">
        Crea disponibilita
      </button>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Slot totali</span>
          <strong class="d-block fs-2 lh-1 mt-2">{{ $totalCount }}</strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Liberi</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-success">{{ $totalFree }}</strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Bloccati</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-danger">{{ $totalBlocked }}</strong>
        </section>
      </div>
    </div>

    @if ($availabilityPreview)
      <section class="portal-panel dashboard-filter-panel">
        <div class="section-heading mb-3">
          <h2>Anteprima slot</h2>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="badge text-bg-primary">{{ $availabilityPreview['creatable']->count() }} da creare</span>
          @if ($availabilityPreview['skipped']->isNotEmpty())
            <span class="badge text-bg-light border">
              {{ $availabilityPreview['skipped']->count() }} {{ $availabilityPreview['skipped']->count() === 1 ? 'slot saltato' : 'slot saltati' }}
            </span>
          @endif
        </div>
        @if ($availabilityPreview['creatable']->isNotEmpty())
          <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach ($availabilityPreview['creatable']->take(12) as $candidate)
              <span class="badge text-bg-light border">{{ $candidate['start_at']->format('d/m H:i') }}</span>
            @endforeach
            @if ($availabilityPreview['creatable']->count() > 12)
              <span class="badge text-bg-secondary">+{{ $availabilityPreview['creatable']->count() - 12 }} altri</span>
            @endif
          </div>
        @endif
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#availabilityBatchModal">
            Modifica
          </button>
          <form method="POST" action="/doctor/availability/batch">
            @csrf
            <input type="hidden" name="start_date" value="{{ $availabilityPreview['input']['start_date'] }}">
            <input type="hidden" name="end_date" value="{{ $availabilityPreview['input']['end_date'] }}">
            <input type="hidden" name="start_time" value="{{ $availabilityPreview['input']['start_time'] }}">
            <input type="hidden" name="end_time" value="{{ $availabilityPreview['input']['end_time'] }}">
            <input type="hidden" name="slot_duration" value="{{ $availabilityPreview['input']['slot_duration'] }}">
            @if ($availabilityPreview['input']['lunch_break_enabled'])
              <input type="hidden" name="lunch_break_enabled" value="1">
              <input type="hidden" name="lunch_break_start" value="{{ $availabilityPreview['input']['lunch_break_start'] }}">
              <input type="hidden" name="lunch_break_end" value="{{ $availabilityPreview['input']['lunch_break_end'] }}">
            @endif
            @foreach ($availabilityPreview['input']['weekdays'] as $weekday)
              <input type="hidden" name="weekdays[]" value="{{ $weekday }}">
            @endforeach
            <button class="btn btn-primary" type="submit" @disabled($availabilityPreview['creatable']->isEmpty())>
              Crea {{ $availabilityPreview['creatable']->count() }} slot
            </button>
          </form>
        </div>
      </section>
    @endif

    <section class="portal-panel dashboard-table-panel">
      <div class="section-heading mb-3">
        <h2>Disponibilita future</h2>
      </div>

      @if ($availabilitySlots->isNotEmpty())
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-2 mb-3" role="tablist">
          @foreach ($availabilitySlots as $slotDate => $slotsForDay)
            @php
              $date = \Carbon\CarbonImmutable::parse($slotDate);
              $tabId = 'availability-day-'.$loop->index;
            @endphp
            <li class="nav-item" role="presentation">
              <button
                class="nav-link border text-nowrap px-3 py-2{{ $loop->first ? ' active' : '' }}"
                id="{{ $tabId }}-tab"
                type="button"
                role="tab"
                data-bs-toggle="tab"
                data-bs-target="#{{ $tabId }}"
                aria-controls="{{ $tabId }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
              >
                <span class="d-block fw-semibold">{{ $weekdayLabels[$date->format('D')] ?? $date->format('D') }}</span>
                <span class="d-block small opacity-75">{{ $date->format('d/m') }}</span>
              </button>
            </li>
          @endforeach
        </ul>

        <div class="tab-content">
          @foreach ($availabilitySlots as $slotDate => $slotsForDay)
            @php
              $date = \Carbon\CarbonImmutable::parse($slotDate);
              $tabId = 'availability-day-'.$loop->index;
            @endphp
            <div
              class="tab-pane fade{{ $loop->first ? ' show active' : '' }}"
              id="{{ $tabId }}"
              role="tabpanel"
              aria-labelledby="{{ $tabId }}-tab"
              tabindex="0"
            >
              <div class="border rounded bg-white p-3">
                <div class="d-flex align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
                  <strong>{{ $date->format('d/m/Y') }}</strong>
                  <span class="badge text-bg-light border">{{ $slotsForDay->count() }} slot</span>
                </div>

                <div class="d-grid gap-2">
                  @foreach ($slotsForDay as $slot)
                    @php
                      $isFree = ! $slot->is_booked && ! $slot->is_blocked;
                      $isBooked = $slot->is_booked;
                      $patientName = $isBooked ? $slot->appointments->first()?->patientName() : null;
                      $slotStateClass = $isFree ? 'free' : ($isBooked ? 'appointment' : 'blocked');
                      $slotLabel = $isFree ? 'Slot libero' : ($isBooked ? ($patientName ?? 'Slot prenotato') : 'Slot bloccato');
                    @endphp
                    <article class="doctor-agenda-item doctor-agenda-item--{{ $slotStateClass }}">
                      <div class="doctor-agenda-item__body">
                        <div class="doctor-agenda-item__main">
                          <div>
                            <h3>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</h3>
                            <p>{{ $slotLabel }}</p>
                          </div>
                          <div class="doctor-agenda-item__actions">
                            @if ($isFree)
                              <span class="badge text-bg-success">Libero</span>
                              <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                              </form>
                            @elseif ($isBooked)
                              <span class="badge text-bg-primary">Prenotato</span>
                            @else
                              <span class="badge text-bg-warning">Bloccato</span>
                              <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                              </form>
                            @endif
                          </div>
                        </div>
                      </div>
                    </article>
                  @endforeach
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessuna disponibilita futura</h3>
          <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#availabilityBatchModal">
            Crea disponibilita
          </button>
        </div>
      @endif
    </section>
  </section>

  <div class="modal fade" id="availabilityBatchModal" tabindex="-1" aria-labelledby="availabilityBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form method="GET" action="/doctor/availability/preview">
          <div class="modal-header">
            <h2 class="modal-title h5" id="availabilityBatchModalLabel">Crea disponibilita</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="availability-start-date">Dal</label>
                <input class="form-control" id="availability-start-date" type="date" name="start_date" value="{{ old('start_date', $batchForm['start_date']) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="availability-end-date">Al</label>
                <input class="form-control" id="availability-end-date" type="date" name="end_date" value="{{ old('end_date', $batchForm['end_date']) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="availability-start-time">Ora inizio</label>
                <input class="form-control" id="availability-start-time" type="time" name="start_time" value="{{ old('start_time', $batchForm['start_time']) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="availability-end-time">Ora fine</label>
                <input class="form-control" id="availability-end-time" type="time" name="end_time" value="{{ old('end_time', $batchForm['end_time']) }}" required>
              </div>
            </div>

            <input type="hidden" name="slot_duration" value="30">

            <fieldset class="mt-4">
              <legend class="form-label">Giorni</legend>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($weekdayOptions as $weekday => $label)
                  <input
                    class="btn-check"
                    id="availability-weekday-{{ $weekday }}"
                    type="checkbox"
                    name="weekdays[]"
                    value="{{ $weekday }}"
                    autocomplete="off"
                    @checked(in_array($weekday, $selectedWeekdays, true))
                  >
                  <label class="btn btn-outline-primary" for="availability-weekday-{{ $weekday }}">{{ $label }}</label>
                @endforeach
              </div>
            </fieldset>

            <fieldset class="border rounded bg-warning-subtle p-3 mt-4">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" id="availability-lunch-break" type="checkbox" name="lunch_break_enabled" value="1" @checked($lunchEnabled)>
                <label class="form-check-label" for="availability-lunch-break">Pausa pranzo</label>
              </div>
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label" for="availability-lunch-start">Pausa inizio</label>
                  <input class="form-control" id="availability-lunch-start" type="time" name="lunch_break_start" value="{{ $lunchStart }}">
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="availability-lunch-end">Pausa fine</label>
                  <input class="form-control" id="availability-lunch-end" type="time" name="lunch_break_end" value="{{ $lunchEnd }}">
                </div>
              </div>
            </fieldset>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-primary">Genera anteprima</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
