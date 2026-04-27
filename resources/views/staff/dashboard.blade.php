@extends('layouts.portal', ['title' => ($mode === 'appointments' ? 'Appuntamenti' : 'Operativita giornaliera').' - MedPortal'])

@php
  $counts = [
    'total' => $appointments->count(),
    'confirmed' => $appointments->where('status', \App\Models\Appointment::STATUS_CONFIRMED)->count(),
    'checked_in' => $appointments->where('status', \App\Models\Appointment::STATUS_CHECKED_IN)->count(),
    'completed' => $appointments->where('status', \App\Models\Appointment::STATUS_COMPLETED)->count(),
    'cancelled' => $appointments->where('status', \App\Models\Appointment::STATUS_CANCELLED)->count(),
  ];
@endphp

@section('content')
  <section class="portal-section operations-dashboard operations-dashboard--wide">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Portale staff</span>
      <h1>{{ $mode === 'appointments' ? 'Appuntamenti' : 'Operativita giornaliera' }}</h1>
      <p>
        {{ $mode === 'appointments'
          ? 'Cerca e gestisci gli appuntamenti per ambulatori, medici, prestazioni e stati.'
          : 'Monitora il flusso operativo di oggi e individua le code che richiedono attenzione.' }}
      </p>
    </div>

    <div class="dashboard-stat-row dashboard-stat-row--three">
      <section class="dashboard-stat">
        <span>Totale</span>
        <strong>{{ $counts['total'] }}</strong>
        <small>visualizzati</small>
      </section>
      <section class="dashboard-stat">
        <span>In attesa</span>
        <strong>{{ $counts['confirmed'] }}</strong>
        <small>confermati</small>
      </section>
      <section class="dashboard-stat">
        <span>Accettati</span>
        <strong>{{ $counts['checked_in'] }}</strong>
        <small>in corso</small>
      </section>
      <section class="dashboard-stat">
        <span>Completati</span>
        <strong>{{ $counts['completed'] }}</strong>
        <small>{{ $counts['cancelled'] }} annullati</small>
      </section>
    </div>

    @if ($mode === 'appointments')
      <section class="portal-panel dashboard-filter-panel">
        <form class="dashboard-filter-grid" method="GET" action="/staff/appointments">
          <label class="form-label">
            Data
            <input class="form-control" type="date" name="date" value="{{ $filters['date'] }}">
          </label>
          <label class="form-label">
            ID ambulatorio
            <input class="form-control" inputmode="numeric" name="clinic" value="{{ $filters['clinic'] }}" placeholder="Qualsiasi">
          </label>
          <label class="form-label">
            ID medico
            <input class="form-control" inputmode="numeric" name="doctor" value="{{ $filters['doctor'] }}" placeholder="Qualsiasi">
          </label>
          <label class="form-label">
            ID prestazione
            <input class="form-control" inputmode="numeric" name="service" value="{{ $filters['service'] }}" placeholder="Qualsiasi">
          </label>
          <label class="form-label">
            Stato
            <select class="form-control" name="status">
              <option value="">Tutti gli stati</option>
              @foreach (\App\Models\Appointment::ALL_STATUSES as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', $status) }}</option>
              @endforeach
            </select>
          </label>
          <div class="dashboard-filter-actions">
            <button type="submit" class="btn btn-primary">Filtra</button>
          </div>
        </form>
      </section>
    @else
      <section class="portal-panel">
        <div class="section-heading">
          <h2>Riepilogo operativo</h2>
          <span>{{ $filters['date'] }}</span>
        </div>
        <div class="operations-summary-grid">
          <div>
            <strong>{{ $counts['confirmed'] }}</strong>
            <span>pazienti attesi</span>
          </div>
          <div>
            <strong>{{ $counts['checked_in'] }}</strong>
            <span>attualmente accettati</span>
          </div>
          <div>
            <strong>{{ $counts['completed'] }}</strong>
            <span>visite completate</span>
          </div>
        </div>
      </section>
    @endif

    @if ($mode === 'appointments')
      <section class="portal-panel dashboard-table-panel">
        <div class="section-heading">
          <h2>Appuntamenti</h2>
          <span>{{ $appointments->count() }} totali</span>
        </div>

        @if ($appointments->isNotEmpty())
          <div class="table-responsive dashboard-table-wrap">
            <table class="table dashboard-table dashboard-table--dense align-middle">
              <thead>
                <tr>
                  <th>Orario</th>
                  <th>Paziente</th>
                  <th>Medico</th>
                  <th>Prestazione</th>
                  <th>Ambulatorio</th>
                  <th>Stato</th>
                  <th>Controlli</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($appointments as $appointment)
                  <tr>
                    <td class="dashboard-table__time">{{ $appointment->start_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $appointment->patientName() }}</td>
                    <td>{{ $appointment->doctor?->display_name ?? 'Medico #'.$appointment->doctor_id }}</td>
                    <td>{{ $appointment->service?->name ?? 'Prestazione #'.$appointment->service_id }}</td>
                    <td>{{ $appointment->clinic?->name ?? 'Ambulatorio #'.$appointment->clinic_id }}</td>
                    <td><x-status-badge :status="$appointment->status" /></td>
                    <td>
                      <div class="status-action-group status-action-group--dense">
                        @if (in_array($appointment->status, [\App\Models\Appointment::STATUS_CONFIRMED, \App\Models\Appointment::STATUS_CHECKED_IN], true))
                          @foreach ([
                            'checked_in' => ['Accetta', 'btn-outline-primary'],
                            'completed' => ['Completa', 'btn-outline-success'],
                            'cancelled' => ['Annulla', 'btn-outline-secondary'],
                            'no_show' => ['Assente', 'btn-outline-danger'],
                          ] as $nextStatus => [$label, $className])
                            @if ($nextStatus !== $appointment->status)
                              <form method="POST" action="/staff/appointments/{{ $appointment->id }}/status">
                                @csrf
                                <input type="hidden" name="status" value="{{ $nextStatus }}">
                                <button class="btn btn-sm {{ $className }}">{{ $label }}</button>
                              </form>
                            @endif
                          @endforeach
                        @else
                          <span class="dashboard-readonly">Nessuna azione</span>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="empty-state">
            <h3>Nessun appuntamento trovato</h3>
            <p>Modifica i filtri per visualizzare un'altra coda di lavoro.</p>
          </div>
        @endif
      </section>
    @endif
  </section>
@endsection
