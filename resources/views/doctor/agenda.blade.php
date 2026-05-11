@extends('layouts.portal', ['title' => 'Agenda - MedPortal'])

@section('content')
  <section class="portal-section operations-dashboard">

    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Agenda</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#closureCreateModal">
          Chiusura
        </button>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#specialOpeningCreateModal">
          Apertura extra
        </button>
      </div>
    </div>

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
            {{ $daySlots->count() }}
          </strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Fatturato Previsto</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-primary">
            &euro; {{ number_format((float) $fatturato, 2, ',', '.') }}
          </strong>
        </section>
      </div>
    </div>
<section class="portal-panel doctor-agenda-panel">
      <div class="week-strip-wrapper mb-3">
        <a class="btn btn-outline-secondary week-nav-arrow"
           href="/doctor/agenda?date={{ $previousWeekStart->toDateString() }}&week_start={{ $previousWeekStart->toDateString() }}"
           aria-label="Settimana precedente">&#8249;</a>

        <div class="week-strip week-strip--agenda">
          @foreach ($weekDays as $weekDay)
            @php
              $dayDateStr = $weekDay['date']->toDateString();
              $isSelected = $dayDateStr === $date;
              $dayClass   = 'week-day' . ($isSelected ? ' week-day--selected' : '');
            @endphp
            <a class="{{ $dayClass }}"
               href="/doctor/agenda?date={{ $dayDateStr }}&week_start={{ $weekStart->toDateString() }}">
              <span class="week-day__name">{{ ucfirst($weekDay['date']->locale('it')->isoFormat('ddd')) }}</span>
              <strong>{{ $weekDay['date']->format('d') }}</strong>
              <span class="week-day__month">{{ ucfirst($weekDay['date']->locale('it')->isoFormat('MMM')) }}</span>
              <span class="availability-dot availability-dot--{{ $weekDay['availabilityState'] }}"></span>
            </a>
          @endforeach
        </div>

        <a class="btn btn-outline-secondary week-nav-arrow"
           href="/doctor/agenda?date={{ $nextWeekStart->toDateString() }}&week_start={{ $nextWeekStart->toDateString() }}"
           aria-label="Settimana successiva">&#8250;</a>
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
                      $appointment      = $item['appointment'];
                      $slot             = $item['slot'];
                      $closure          = $item['closure'];
                      $state            = $item['state'];
                      $start            = $item['start_at'];
                      $hasStarted       = $start->lessThanOrEqualTo($currentTime);
                      $showPassatoBadge = $hasStarted && ! $isSelectedToday;
                      $itemClass        = $item['type'] === 'appointment' ? 'appointment' : $state;
                      $spanRows         = $item['span_rows'] ?? 1;
                      $itemClasses      = 'doctor-agenda-item doctor-agenda-item--'.$itemClass.($spanRows > 1 ? ' doctor-agenda-item--spanning' : '');
                    @endphp

                    <article class="{{ $itemClasses }}"
                      @if ($closure)
                        data-agenda-closure-id="{{ $closure->id }}"
                        style="--agenda-span-rows: {{ $spanRows }};"
                      @endif
                    >
                      @if ($appointment)
                        @php
                          $appointmentCancelModalId = "appointmentCancelModal{$appointment->id}";
                          $canCancelAppointment = in_array($appointment->status, \App\Models\Appointment::ACTIVE_SLOT_STATUSES, true)
                            && $appointment->start_at->isFuture();
                        @endphp
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
                              @if ($canCancelAppointment)
                                <button
                                  class="btn btn-sm btn-outline-danger"
                                  type="button"
                                  data-bs-toggle="modal"
                                  data-bs-target="#{{ $appointmentCancelModalId }}"
                                  aria-label="Annulla appuntamento"
                                  title="Annulla appuntamento"
                                ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                              @endif
                            </div>
                          </div>
                        </div>
                        @push('modals')
                          @include('doctor.partials.appointment-info-modal', ['appointment' => $appointment])
                          @if ($canCancelAppointment)
                            <x-appointment-cancel-modal
                              :appointment="$appointment"
                              :action="'/doctor/appointments/'.$appointment->id.'/cancel'"
                              :modal-id="$appointmentCancelModalId"
                            />
                          @endif
                        @endpush
                      @else
                        @php
                          $slotTitle = $closure
                            ? ($closure->reason ?: 'Chiusura')
                            : 'Slot libero';
                          $closureRange = null;
                          if ($closure) {
                            $closureRange = $closure->start_time
                              ? ($item['closure_start_at']->format('H:i').' - '.$item['closure_end_at']->format('H:i'))
                              : 'Tutto il giorno';
                          }
                        @endphp
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main">
                            <div>
                              <h3>{{ $slotTitle }}</h3>
                              @if ($closureRange)
                                <p>{{ $closureRange }}</p>
                              @endif
                            </div>
                            <div class="doctor-agenda-item__actions">
                              @if ($showPassatoBadge)
                                <span class="badge badge-neutral">Passato</span>
                              @elseif ($state === 'blocked' && ! $hasStarted && $closure)
                                <form method="POST" action="/doctor/closures/{{ $closure->id }}">
                                  @csrf
                                  @method('DELETE')
                                  <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-primary"
                                    aria-label="Riapri disponibilita"
                                    title="Riapri disponibilita"
                                  ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11 1a2 2 0 0 0-2 2v3h1V3a1 1 0 0 1 2 0v1.5a.5.5 0 0 0 1 0V3a2 2 0 0 0-2-2"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v6A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 11.5 6zm0 1h7a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-6a.5.5 0 0 1 .5-.5"/></svg></button>
                                </form>
                              @elseif ($state === 'free' && ! $hasStarted)
                                <form method="POST" action="/doctor/availability/block">
                                  @csrf
                                  <input type="hidden" name="slot_start" value="{{ $slot->key }}">
                                  <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    aria-label="Blocca slot libero"
                                    title="Blocca slot libero"
                                  ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M11.354 4.646a.5.5 0 0 0-.708-.708l-6.708 6.708a.5.5 0 0 0 .708.708z"/></svg></button>
                                </form>
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
    
    <section class="card mb-3 schedule-events-panel">
      <div class="card-header border-bottom-0">
        <h2 class="h5 mb-0">Prossimi eventi</h2>
      </div>

      @if ($upcomingScheduleEvents->isEmpty())
        <div class="card-body">
          <p class="card-text text-body-secondary mb-0">Nessun evento programmato.</p>
        </div>
      @else
        <div class="card-body">
          <div class="vstack gap-3">
            @foreach ($upcomingScheduleEvents as $event)
              @php
                $model = $event['model'];
                $modalId = $event['type'].'Modal'.$model->id;
                $deleteModalId = $event['type'] === 'closure'
                  ? "deleteScheduleEventModalClosure{$model->id}"
                  : "deleteScheduleEventModalSpecialOpening{$model->id}";
                $timeLabel = $event['start_time']
                  ? "{$event['start_time']} - {$event['end_time']}"
                  : 'Tutto il giorno';
                $eventTypeClass = $event['type'] === 'closure' ? 'closure' : 'special-opening';
                $eventTypeLabel = $event['type'] === 'closure' ? 'Chiusura' : 'Apertura extra';
              @endphp
              <article class="schedule-event-card schedule-event-card--{{ $eventTypeClass }}">
                <div class="schedule-event-card__main">
                  <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                      <span class="schedule-event-card__pill schedule-event-card__pill--{{ $eventTypeClass }}">{{ $eventTypeLabel }}</span>
                      <span class="schedule-event-card__meta">
                        {{ ucfirst($event['date']->locale('it')->isoFormat('ddd D MMM')) }} &middot; {{ $timeLabel }}
                      </span>
                    </div>
                    <h3>{{ $event['title'] }}</h3>
                  </div>
                  <div class="schedule-event-card__actions" role="group" aria-label="Azioni evento">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" aria-label="Modifica evento">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 3 10.707V13h2.293z"/>
                      </svg>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#{{ $deleteModalId }}" aria-label="Elimina evento">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                        <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </article>
            @endforeach
          </div>
        </div>
      @endif
    </section>

    

  </section>

  <div class="modal fade" id="closureCreateModal" tabindex="-1" aria-labelledby="closureCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/doctor/closures">
          @csrf
          <div class="modal-header">
            <h2 class="modal-title h5" id="closureCreateModalLabel">Chiusura</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
          </div>
          <div class="modal-body">
            @error('closure') <div class="alert alert-danger">{{ $message }}</div> @enderror
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="closure-date">Dal</label>
                <input class="form-control" id="closure-date" type="date" name="date" value="{{ old('date', $date) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-end-date">Al</label>
                <input class="form-control" id="closure-end-date" type="date" name="end_date" value="{{ old('end_date', $date) }}">
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" id="closure-all-day" type="checkbox" name="all_day" value="1" checked>
                  <label class="form-check-label" for="closure-all-day">Tutto il giorno</label>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-start-time">Ora inizio</label>
                <x-time-select class="form-control" id="closure-start-time" name="start_time" :value="old('start_time')" />
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-end-time">Ora fine</label>
                <x-time-select class="form-control" id="closure-end-time" name="end_time" :value="old('end_time')" :include-end-of-day="true" />
              </div>
              <div class="col-12">
                <label class="form-label" for="closure-reason">Motivo</label>
                <input class="form-control" id="closure-reason" name="reason" value="{{ old('reason') }}" placeholder="Ferie, congresso, riunione">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-danger">Salva chiusura</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="specialOpeningCreateModal" tabindex="-1" aria-labelledby="specialOpeningCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/doctor/special-openings">
          @csrf
          <div class="modal-header">
            <h2 class="modal-title h5" id="specialOpeningCreateModalLabel">Apertura extra</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
          </div>
          <div class="modal-body">
            @error('special_opening') <div class="alert alert-danger">{{ $message }}</div> @enderror
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="special-opening-date">Data</label>
                <input class="form-control" id="special-opening-date" type="date" name="date" value="{{ old('date', $date) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="special-opening-start">Ora inizio</label>
                <x-time-select class="form-control" id="special-opening-start" name="start_time" :value="old('start_time', '09:00')" required />
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="special-opening-end">Ora fine</label>
                <x-time-select class="form-control" id="special-opening-end" name="end_time" :value="old('end_time', '12:00')" :include-end-of-day="true" required />
              </div>
              <div class="col-12">
                <label class="form-label" for="special-opening-note">Nota</label>
                <input class="form-control" id="special-opening-note" name="note" value="{{ old('note') }}" placeholder="Open day, recupero visite">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-primary">Salva apertura</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @foreach ($upcomingScheduleEvents as $event)
    @php
      $model = $event['model'];
      $modalId = $event['type'].'Modal'.$model->id;
      $deleteModalId = $event['type'] === 'closure'
        ? "deleteScheduleEventModalClosure{$model->id}"
        : "deleteScheduleEventModalSpecialOpening{$model->id}";
      $deleteAction = $event['type'] === 'closure'
        ? "/doctor/closures/{$model->id}"
        : "/doctor/special-openings/{$model->id}";
      $deleteMessage = $event['type'] === 'closure'
        ? 'Questa chiusura verrà eliminata.'
        : 'Questa apertura extra verrà eliminata.';
    @endphp
    <div class="modal fade" id="{{ $deleteModalId }}" tabindex="-1" aria-labelledby="{{ $deleteModalId }}Label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="{{ $deleteAction }}">
            @csrf
            @method('DELETE')
            <div class="modal-header">
              <h2 class="modal-title fs-5" id="{{ $deleteModalId }}Label">Conferma eliminazione</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi conferma eliminazione"></button>
            </div>
            <div class="modal-body">
              <p>{{ $deleteMessage }}</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
              <button type="submit" class="btn btn-danger">Sì, elimina</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          @if ($event['type'] === 'closure')
            <form method="POST" action="/doctor/closures/{{ $model->id }}">
              @csrf
              @method('PATCH')
              <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Label">Modifica chiusura</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
              </div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Data</label>
                    <input class="form-control" type="date" name="date" value="{{ $event['date']->toDateString() }}" required>
                  </div>
                  <div class="col-12">
                    <div class="form-check form-switch">
                      <input class="form-check-input" id="{{ $modalId }}AllDay" type="checkbox" name="all_day" value="1" @checked(! $event['start_time'])>
                      <label class="form-check-label" for="{{ $modalId }}AllDay">Tutto il giorno</label>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Ora inizio</label>
                    <x-time-select class="form-control" name="start_time" :value="$event['start_time']" />
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Ora fine</label>
                    <x-time-select class="form-control" name="end_time" :value="$event['end_time']" :include-end-of-day="true" />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Motivo</label>
                    <input class="form-control" name="reason" value="{{ $model->reason }}">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="submit" class="btn btn-primary">Salva</button>
              </div>
            </form>
          @else
            <form method="POST" action="/doctor/special-openings/{{ $model->id }}">
              @csrf
              @method('PATCH')
              <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Label">Modifica apertura extra</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
              </div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Data</label>
                    <input class="form-control" type="date" name="date" value="{{ $event['date']->toDateString() }}" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Ora inizio</label>
                    <x-time-select class="form-control" name="start_time" :value="$event['start_time']" required />
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Ora fine</label>
                    <x-time-select class="form-control" name="end_time" :value="$event['end_time']" :include-end-of-day="true" required />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Nota</label>
                    <input class="form-control" name="note" value="{{ $model->note }}">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="submit" class="btn btn-primary">Salva</button>
              </div>
            </form>
          @endif
        </div>
      </div>
    </div>
  @endforeach
@endsection
