@extends('layouts.portal', ['title' => 'Agenda - MedPortal'])

@php
  $selectedDay     = \Carbon\CarbonImmutable::parse($date);
  $selectedDayLabel = ucfirst($selectedDay->locale('it')->isoFormat('dddd DD/MM/YYYY'));
  $previousDayUrl  = request()->fullUrlWithQuery(['date' => $selectedDay->subDay()->toDateString()]);
  $nextDayUrl      = request()->fullUrlWithQuery(['date' => $selectedDay->addDay()->toDateString()]);

  $weekdayOptions   = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN'];
  $selectedWeekdays = old('weekdays', $batchForm['weekdays'] ?? [1, 2, 3, 4, 5]);
  $selectedWeekdays = is_array($selectedWeekdays) ? array_map('intval', $selectedWeekdays) : [1, 2, 3, 4, 5];
  $lunchEnabled     = old('lunch_break_enabled', $batchForm['lunch_break_enabled'] ?? false);
  $lunchStart       = old('lunch_break_start', $batchForm['lunch_break_start'] ?? '13:00');
  $lunchEnd         = old('lunch_break_end',   $batchForm['lunch_break_end']   ?? '14:00');
@endphp

@section('content')
  <section class="portal-section operations-dashboard">

    {{-- Header --}}
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Agenda</h1>
      </div>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#availabilityBatchModal">
        Crea disponibilita
      </button>
    </div>

    {{-- 3 Stat cards --}}
    <div class="row g-3 mb-3">
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Appuntamenti</span>
          <strong class="d-block fs-2 lh-1 mt-2">{{ $appointments->count() }}</strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Slot disponibili</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-success">
            {{ $daySlots->filter(fn ($s) => ! $s->is_booked && ! $s->is_blocked)->count() }}
          </strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Fatturato</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-primary">
            &euro; {{ number_format((float) $fatturato, 2, ',', '.') }}
          </strong>
        </section>
      </div>
    </div>

    {{-- Agenda panel --}}
    <section class="portal-panel doctor-agenda-panel">
      <div class="section-heading">
        <div>
          <h2>Agenda del giorno</h2>
          <p>{{ $selectedDayLabel }}</p>
        </div>
        <form method="GET" action="/doctor/agendav2" class="dashboard-date-filter dashboard-day-nav">
          <div class="dashboard-day-nav__controls">
            <a class="btn btn-outline-secondary dashboard-day-nav__arrow"
               href="{{ $previousDayUrl }}"
               aria-label="Giorno precedente">&#8249;</a>
            @foreach (request()->except('date') as $queryName => $queryValue)
              @foreach (\Illuminate\Support\Arr::wrap($queryValue) as $queryItem)
                <input type="hidden" name="{{ is_array($queryValue) ? "{$queryName}[]" : $queryName }}" value="{{ $queryItem }}">
              @endforeach
            @endforeach
            <input class="form-control" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
            <a class="btn btn-outline-secondary dashboard-day-nav__arrow"
               href="{{ $nextDayUrl }}"
               aria-label="Giorno successivo">&#8250;</a>
          </div>
        </form>
      </div>

      <div class="doctor-agenda-scroll" data-agenda-scroll-container aria-label="Agenda completa della giornata">
        <div class="doctor-agenda-grid">
          @foreach ($agendaRows as $row)
            @php
              $rowClasses = 'doctor-agenda-row' . ($row['is_past'] ? ' doctor-agenda-row--past' : '');
            @endphp
            <div class="{{ $rowClasses }}" data-time="{{ $row['label'] }}" @if ($row['items']->isNotEmpty()) data-agenda-occupied-row @endif>
              <time class="doctor-agenda-row__time" datetime="{{ $row['start_at']->toIso8601String() }}">
                {{ $row['label'] }}
              </time>

              @if ($row['items']->isEmpty() && ! $row['is_current'])
                <div class="doctor-agenda-row__content" aria-hidden="true"></div>
              @else
                <div class="doctor-agenda-row__content">
                  @if ($row['is_current'])
                    <div class="doctor-agenda-now-marker" data-agenda-now-marker style="--now-position: {{ $row['now_position'] }}%;">
                      <span>Ora {{ $currentTime->format('H:i') }}</span>
                    </div>
                  @endif

                  @foreach ($row['items'] as $item)
                    @php
                      $appointment     = $item['appointment'];
                      $slot            = $item['slot'];
                      $state           = $item['state'];
                      $start           = $item['start_at'];
                      $hasStarted      = $start->lessThanOrEqualTo($currentTime);
                      $showPassatoBadge = $hasStarted && ! $isSelectedToday;
                      $itemClass       = $item['type'] === 'appointment' ? 'appointment' : $state;
                    @endphp

                    <article class="doctor-agenda-item doctor-agenda-item--{{ $itemClass }}">
                      @if ($appointment)
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $appointment->patientName() }}</h3>
                              <p>{{ $appointment->service?->name ?? 'Appuntamento' }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#appointmentInfoModal{{ $appointment->id }}"
                                aria-label="Informazioni appuntamento"
                              ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg></button>
                            </div>
                          </div>
                          @if (! $slot)
                            <div class="doctor-agenda-item__meta">
                              <span>Slot non collegato</span>
                            </div>
                          @endif
                        </div>
                        @push('modals')
                          @include('doctor.partials.appointment-info-modal', ['appointment' => $appointment])
                        @endpush
                      @else
                        @php
                          $slotTitle = match ($state) {
                            'blocked' => 'Slot bloccato',
                            'booked'  => 'Slot prenotato',
                            default   => 'Slot libero',
                          };
                        @endphp
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $slotTitle }}</h3>
                            </div>
                            <div class="doctor-agenda-item__actions">
                              @if ($showPassatoBadge)
                                <span class="badge text-bg-secondary">Passato</span>
                              @elseif ($state === 'blocked' && ! $hasStarted)
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                                </form>
                              @elseif ($state === 'free' && ! $hasStarted)
                                <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                                  @csrf
                                  <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                                </form>
                              @elseif ($state === 'booked')
                                <span class="badge text-bg-secondary">Prenotato</span>
                              @endif
                            </div>
                          </div>
                        </div>
                      @endif
                    </article>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </section>

  </section>

  {{-- ════ Crea disponibilita modal ════ --}}
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

        <form id="av2-preview-form" method="GET" action="/doctor/availability/preview">
          <input type="hidden" name="source" value="agendav2">
          <div class="modal-body">
            <div class="availability-step availability-step--form{{ $availabilityPreview ? ' d-none' : '' }}" data-availability-form-step>
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label" for="av2-start-date">Dal</label>
                  <input class="form-control" id="av2-start-date" type="date" name="start_date" value="{{ old('start_date', $batchForm['start_date']) }}" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="av2-end-date">Al</label>
                  <input class="form-control" id="av2-end-date" type="date" name="end_date" value="{{ old('end_date', $batchForm['end_date']) }}" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="av2-start-time">Ora inizio</label>
                  <input class="form-control" id="av2-start-time" type="time" name="start_time" value="{{ old('start_time', $batchForm['start_time']) }}" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="av2-end-time">Ora fine</label>
                  <input class="form-control" id="av2-end-time" type="time" name="end_time" value="{{ old('end_time', $batchForm['end_time']) }}" required>
                </div>
              </div>

              <input type="hidden" name="slot_duration" value="30">

              <fieldset class="mt-4">
                <legend class="form-label">Giorni</legend>
                <div class="d-flex flex-wrap gap-2">
                  @foreach ($weekdayOptions as $weekday => $label)
                    <input
                      class="btn-check"
                      id="av2-weekday-{{ $weekday }}"
                      type="checkbox"
                      name="weekdays[]"
                      value="{{ $weekday }}"
                      autocomplete="off"
                      @checked(in_array($weekday, $selectedWeekdays, true))
                    >
                    <label class="btn btn-outline-primary" for="av2-weekday-{{ $weekday }}">{{ $label }}</label>
                  @endforeach
                </div>
              </fieldset>

              <fieldset class="availability-lunch-card{{ $lunchEnabled ? '' : ' availability-lunch-card--collapsed' }}" data-availability-lunch-card>
                <div class="form-check form-switch availability-lunch-card__toggle">
                  <input
                    class="form-check-input"
                    id="av2-lunch-break"
                    type="checkbox"
                    name="lunch_break_enabled"
                    value="1"
                    data-availability-lunch-toggle
                    @checked($lunchEnabled)
                  >
                  <label class="form-check-label" for="av2-lunch-break">Pausa pranzo</label>
                </div>
                <div class="row g-3 availability-lunch-card__fields" data-availability-lunch-fields>
                  <div class="col-sm-6">
                    <label class="form-label" for="av2-lunch-start">Pausa inizio</label>
                    <input class="form-control" id="av2-lunch-start" type="time" name="lunch_break_start" value="{{ $lunchStart }}" @disabled(! $lunchEnabled)>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="av2-lunch-end">Pausa fine</label>
                    <input class="form-control" id="av2-lunch-end" type="time" name="lunch_break_end" value="{{ $lunchEnd }}" @disabled(! $lunchEnabled)>
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
                  form="av2-create-form"
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
          <form id="av2-create-form" class="d-none" method="POST" action="/doctor/availability/batch">
            @csrf
            <input type="hidden" name="_source" value="agendav2">
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
