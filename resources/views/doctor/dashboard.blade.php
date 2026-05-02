@extends('layouts.portal', ['title' => 'Agenda - MedPortal'])

@php
  $dayFreeSlots = $daySlots->filter(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked)->count();
  $selectedDay = \Carbon\CarbonImmutable::parse($date);
  $selectedDayLabel = ucfirst($selectedDay->locale('it')->isoFormat('dddd DD/MM/YYYY'));
  $previousDayUrl = request()->fullUrlWithQuery(['date' => $selectedDay->subDay()->toDateString()]);
  $nextDayUrl = request()->fullUrlWithQuery(['date' => $selectedDay->addDay()->toDateString()]);
@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Agenda</h1>
      </div>
    </div>

    <div class="dashboard-stat-row dashboard-stat-row--three">
      <section class="dashboard-stat">
        <span>Appuntamenti</span>
        <strong>{{ $appointments->count() }}</strong>
      </section>
      <section class="dashboard-stat">
        <span>Slot liberi</span>
        <strong>{{ $dayFreeSlots }}</strong>
      </section>
      <section class="dashboard-stat dashboard-stat--day-nav">
        <form method="GET" action="/doctor/schedule" class="dashboard-date-filter dashboard-day-nav">
          <span>Data</span>
          <div class="dashboard-day-nav__controls">
            <a class="btn btn-outline-secondary dashboard-day-nav__arrow"
              href="{{ $previousDayUrl }}"
              aria-label="Giorno precedente">‹</a>
            @foreach (request()->except('date') as $queryName => $queryValue)
              @foreach (\Illuminate\Support\Arr::wrap($queryValue) as $queryItem)
                <input type="hidden" name="{{ is_array($queryValue) ? "{$queryName}[]" : $queryName }}" value="{{ $queryItem }}">
              @endforeach
            @endforeach
            <input class="form-control" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
            <a class="btn btn-outline-secondary dashboard-day-nav__arrow"
              href="{{ $nextDayUrl }}"
              aria-label="Giorno successivo">›</a>
          </div>
        </form>
      </section>
    </div>

    <section class="portal-panel doctor-agenda-panel">
      <div class="section-heading">
        <div>
          <h2>Agenda del giorno</h2>
          <p>{{ $selectedDayLabel }}</p>
        </div>
      </div>

      <div class="doctor-agenda-scroll" data-agenda-scroll-container aria-label="Agenda completa della giornata">
        <div class="doctor-agenda-grid">
          @foreach ($agendaRows as $row)
            @php
              $rowClasses = 'doctor-agenda-row'.($row['is_past'] ? ' doctor-agenda-row--past' : '');
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
                      $appointment = $item['appointment'];
                      $slot = $item['slot'];
                      $state = $item['state'];
                      $start = $item['start_at'];
                      $end = $item['end_at'];
                      $hasStarted = $start->lessThanOrEqualTo($currentTime);
                      $showPassatoBadge = $hasStarted && ! $isSelectedToday;
                      $itemClass = $item['type'] === 'appointment' ? 'appointment' : $state;
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
                            'booked' => 'Slot prenotato',
                            default => 'Slot libero',
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
@endsection
