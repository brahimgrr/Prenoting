@extends('layouts.portal', ['title' => 'Agenda - MedPortal'])

@php
  $confirmedCount = $appointments->where('status', \App\Models\Appointment::STATUS_CONFIRMED)->count();
  $checkedInCount = $appointments->where('status', \App\Models\Appointment::STATUS_CHECKED_IN)->count();
  $dayFreeSlots = $daySlots->filter(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked)->count();
  $dayBookedSlots = $daySlots->where('is_booked', true)->count();
  $dayBlockedSlots = $daySlots->where('is_blocked', true)->count();
@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Agenda</h1>
        <p>Visualizza la giornata in ordine cronologico e gestisci gli slot liberi.</p>
      </div>
      <form method="GET" action="/doctor/schedule" class="dashboard-date-filter">
        <span>Data</span>
        <input class="form-control" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
      </form>
    </div>

    <div class="dashboard-stat-row dashboard-stat-row--three">
      <section class="dashboard-stat">
        <span>Appuntamenti</span>
        <strong>{{ $appointments->count() }}</strong>
        <small>{{ $date === now()->toDateString() ? 'oggi' : $date }}</small>
      </section>
      <section class="dashboard-stat">
        <span>Slot liberi</span>
        <strong>{{ $dayFreeSlots }}</strong>
        <small>{{ $dayBookedSlots }} prenotati</small>
      </section>
      <section class="dashboard-stat">
        <span>Bloccati</span>
        <strong>{{ $dayBlockedSlots }}</strong>
        <small>{{ $confirmedCount }} confermati, {{ $checkedInCount }} in corso</small>
      </section>
    </div>

    <section class="portal-panel doctor-agenda-panel">
      <div class="section-heading">
        <div>
          <h2>Agenda del giorno</h2>
          <p>{{ \Carbon\CarbonImmutable::parse($date)->format('d/m/Y') }}</p>
        </div>
        <span>{{ $timelineItems->count() }} {{ $timelineItems->count() === 1 ? 'elemento' : 'elementi' }}</span>
      </div>

      @if ($timelineItems->isNotEmpty())
        <div class="doctor-agenda-timeline">
          @foreach ($timelineItems as $item)
            @php
              $appointment = $item['appointment'];
              $slot = $item['slot'];
              $state = $item['state'];
              $start = $item['start_at'];
              $end = $item['end_at'];
              $isPast = $start->isPast();
              $rowClass = $item['type'] === 'appointment' ? 'appointment' : $state;
            @endphp

            <article class="doctor-agenda-item doctor-agenda-item--{{ $rowClass }}">
              <time class="doctor-agenda-item__time" datetime="{{ $start->toIso8601String() }}">
                {{ $start->format('H:i') }}
              </time>

              @if ($appointment)
                <div class="doctor-agenda-item__body">
                  <div class="doctor-agenda-item__main">
                    <div>
                      <h3>{{ $appointment->patientName() }}</h3>
                      <p>{{ $appointment->service?->name ?? 'Appuntamento' }}</p>
                    </div>
                  </div>
                  <div class="doctor-agenda-item__meta">
                    <span>{{ $start->format('H:i') }} - {{ $end->format('H:i') }}</span>
                    @if (! $slot)
                      <span>Slot non collegato</span>
                    @endif
                  </div>
                </div>
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
                      <p>{{ $start->format('H:i') }} - {{ $end->format('H:i') }}</p>
                    </div>
                    <div class="doctor-agenda-item__actions">
                      @if ($isPast)
                        <span class="badge text-bg-secondary">Passato</span>
                      @elseif ($state === 'blocked')
                        <span class="badge text-bg-warning">Bloccato</span>
                        <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                        </form>
                      @elseif ($state === 'free')
                        <span class="badge text-bg-success">Libero</span>
                        <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                        </form>
                      @else
                        <span class="badge text-bg-secondary">Prenotato</span>
                      @endif
                    </div>
                  </div>
                </div>
              @endif
            </article>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessun elemento in agenda</h3>
          <p>Non ci sono appuntamenti o disponibilita per questa data.</p>
        </div>
      @endif
    </section>

  </section>
@endsection
