@extends('layouts.portal', ['title' => 'Disponibilita - MedPortal'])

@php
  $availabilityTotals = $availabilitySlots->flatten(1);
  $totalFree = $availabilityTotals->filter(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked)->count();
  $totalBooked = $availabilityTotals->where('is_booked', true)->count();
  $totalCount = $availabilityTotals->count();

  $weekdayOptions = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN'];
  $selectedWeekdays = old('weekdays', $batchForm['weekdays'] ?? [1, 2, 3, 4, 5]);
  $selectedWeekdays = is_array($selectedWeekdays) ? array_map('intval', $selectedWeekdays) : [1, 2, 3, 4, 5];

  $lunchEnabled = old('lunch_break_enabled', $batchForm['lunch_break_enabled'] ?? false);
  $lunchStart = old('lunch_break_start', $batchForm['lunch_break_start'] ?? '13:00');
  $lunchEnd = old('lunch_break_end', $batchForm['lunch_break_end'] ?? '14:00');

@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Gestisci disponibilita</span>
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
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Prenotati</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-primary">{{ $totalBooked }}</strong>
        </section>
      </div>
    </div>

    <section class="portal-panel dashboard-table-panel">
      @if ($availabilitySlots->isNotEmpty())
        <div class="avail-tabs-wrap">
          <div class="week-strip" role="tablist" aria-label="Giorni con disponibilita">
            @foreach ($availabilitySlots as $slotDate => $slotsForDay)
              @php
                $date = \Carbon\CarbonImmutable::parse($slotDate);
                $tabId = 'availability-day-'.$loop->index;
                $hasFreeSlots = $slotsForDay->contains(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked);
              @endphp
              <button
                class="week-day avail-day-tab{{ $loop->first ? ' active week-day--selected' : '' }}"
                id="{{ $tabId }}-tab"
                type="button"
                role="tab"
                data-bs-toggle="tab"
                data-bs-target="#{{ $tabId }}"
                data-availability-day-tab
                aria-controls="{{ $tabId }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
              >
                <span class="week-day__name">{{ ucfirst($date->locale('it')->isoFormat('ddd')) }}</span>
                <strong>{{ $date->format('d') }}</strong>
                <span class="week-day__month">{{ ucfirst($date->locale('it')->isoFormat('MMM')) }}</span>
                <span class="availability-dot {{ $hasFreeSlots ? 'availability-dot--open' : 'availability-dot--closed' }}"></span>
              </button>
            @endforeach
          </div>
        </div>

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
              <div class="avail-slot-panel">
                <div class="d-flex align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
                  <strong>{{ $date->format('d/m/Y') }}</strong>
                  <span class="badge text-bg-light border">{{ $slotsForDay->count() }} slot</span>
                </div>

                <div class="d-grid gap-2">
                  @foreach ($slotsForDay as $slot)
                    @php
                      $isFree = ! $slot->is_booked && ! $slot->is_blocked;
                      $isBooked = $slot->is_booked;
                      $infoAppt = $isBooked ? $slot->appointments->first() : null;
                      $patientName = $infoAppt?->patientName();
                      $slotStateClass = $isFree ? 'free' : ($isBooked ? 'appointment' : 'blocked');
                      $slotLabel = $isFree ? 'Slot libero' : ($isBooked ? ($patientName ?? 'Slot prenotato') : 'Slot bloccato');
                    @endphp
                    <div class="avail-slot-row">
                      <article class="doctor-agenda-item doctor-agenda-item--{{ $slotStateClass }}">
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</h3>
                              <p>{{ $slotLabel }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              @if ($isFree)
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                                </form>
                              @elseif ($isBooked && $infoAppt)
                                <button
                                  class="btn btn-sm btn-outline-secondary"
                                  type="button"
                                  data-bs-toggle="modal"
                                  data-bs-target="#appointmentInfoModal{{ $infoAppt->id }}"
                                  aria-label="Informazioni appuntamento"
                                ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg></button>
                              @else
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                                </form>
                              @endif
                            </div>
                          </div>
                        </div>
                      </article>
                    </div>
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

  @push('modals')
    @foreach ($availabilitySlots as $slotsForDay)
      @foreach ($slotsForDay as $slot)
        @php
          $infoAppt = $slot->is_booked ? $slot->appointments->first() : null;
        @endphp
        @if ($infoAppt)
          @include('doctor.partials.appointment-info-modal', ['appointment' => $infoAppt])
        @endif
      @endforeach
    @endforeach
  @endpush

  <div
    class="modal fade"
    id="availabilityBatchModal"
    tabindex="-1"
    aria-labelledby="availabilityBatchModalLabel"
    aria-hidden="true"
    @if ($availabilityPreview) data-availability-preview-open @endif
  >
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="availabilityBatchModalLabel">Crea disponibilita</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>

        <form id="availability-preview-form" method="GET" action="/doctor/availability/preview">
          <div class="modal-body">
            <div class="availability-step availability-step--form{{ $availabilityPreview ? ' d-none' : '' }}" data-availability-form-step>
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

              <fieldset class="availability-lunch-card{{ $lunchEnabled ? '' : ' availability-lunch-card--collapsed' }}" data-availability-lunch-card>
                <div class="form-check form-switch availability-lunch-card__toggle">
                  <input
                    class="form-check-input"
                    id="availability-lunch-break"
                    type="checkbox"
                    name="lunch_break_enabled"
                    value="1"
                    data-availability-lunch-toggle
                    @checked($lunchEnabled)
                  >
                  <label class="form-check-label" for="availability-lunch-break">Pausa pranzo</label>
                </div>
                <div class="row g-3 availability-lunch-card__fields" data-availability-lunch-fields>
                  <div class="col-sm-6">
                    <label class="form-label" for="availability-lunch-start">Pausa inizio</label>
                    <input class="form-control" id="availability-lunch-start" type="time" name="lunch_break_start" value="{{ $lunchStart }}" @disabled(! $lunchEnabled)>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="availability-lunch-end">Pausa fine</label>
                    <input class="form-control" id="availability-lunch-end" type="time" name="lunch_break_end" value="{{ $lunchEnd }}" @disabled(! $lunchEnabled)>
                  </div>
                </div>
              </fieldset>
            </div>

            @if ($availabilityPreview)
              <div class="availability-step availability-step--preview" data-availability-preview-step>
                <section class="availability-preview-card" aria-live="polite">
                  <div>
                    <h3 class="h6 mb-1">Anteprima slot</h3>
                    <p class="text-body-secondary small mb-0">
                      {{ $availabilityPreview['weekdayLabels'] }} &middot; {{ $availabilityPreview['input']['start_time'] }}-{{ $availabilityPreview['input']['end_time'] }}
                    </p>
                  </div>

                  <div class="availability-preview-stats">
                    <div>
                      <strong>{{ $availabilityPreview['creatable']->count() }}</strong>
                      <span>Da creare</span>
                    </div>
                  </div>

                  @if ($availabilityPreview['creatable']->isNotEmpty())
                    <div class="availability-preview-list" aria-label="Slot che verranno creati">
                      @foreach ($availabilityPreview['creatable']->take(12) as $candidate)
                        <span>{{ $candidate['start_at']->format('d/m H:i') }}</span>
                      @endforeach
                      @if ($availabilityPreview['creatable']->count() > 12)
                        <span>+{{ $availabilityPreview['creatable']->count() - 12 }} altri</span>
                      @endif
                    </div>
                  @endif
                </section>
              </div>
            @endif
          </div>

          <div class="modal-footer">
            <div class="availability-modal-actions{{ $availabilityPreview ? ' d-none' : '' }}" data-availability-form-actions>
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
              <button type="submit" class="btn btn-primary">Genera anteprima</button>
            </div>
            <div class="availability-modal-actions{{ $availabilityPreview ? '' : ' d-none' }}" data-availability-preview-actions>
              @if ($availabilityPreview)
                <button type="button" class="btn btn-outline-secondary" data-availability-edit-preview>Modifica</button>
                <button
                  type="submit"
                  form="availability-create-form"
                  class="btn btn-primary"
                  @disabled($availabilityPreview['creatable']->isEmpty())
                >
                  Crea {{ $availabilityPreview['creatable']->count() }} slot
                </button>
              @endif
            </div>
          </div>
        </form>

        @if ($availabilityPreview)
          <form id="availability-create-form" class="d-none" method="POST" action="/doctor/availability/batch">
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
          </form>
        @endif
      </div>
    </div>
  </div>
@endsection
