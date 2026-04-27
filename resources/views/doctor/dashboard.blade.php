@extends('layouts.portal', ['title' => ($mode === 'schedule' ? 'Agenda' : 'Oggi').' - MedPortal'])

@php
  $confirmedCount = $appointments->where('status', \App\Models\Appointment::STATUS_CONFIRMED)->count();
  $checkedInCount = $appointments->where('status', \App\Models\Appointment::STATUS_CHECKED_IN)->count();
  $completedCount = $appointments->where('status', \App\Models\Appointment::STATUS_COMPLETED)->count();
@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>{{ $mode === 'schedule' ? 'Agenda' : 'Oggi' }}</h1>
        <p>
          {{ $mode === 'schedule'
            ? 'Consulta gli appuntamenti di una data e aggiorna lo stato delle visite.'
            : 'Segui il flusso delle visite di oggi e concentrati sui prossimi pazienti.' }}
        </p>
      </div>
      @if ($mode === 'schedule')
        <form method="GET" action="/doctor/schedule" class="dashboard-date-filter">
          <span>Data</span>
          <input class="form-control" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </form>
      @endif
    </div>

    <div class="dashboard-stat-row dashboard-stat-row--three">
      <section class="dashboard-stat">
        <span>Appuntamenti</span>
        <strong>{{ $appointments->count() }}</strong>
        <small>{{ $date === now()->toDateString() ? 'oggi' : $date }}</small>
      </section>
      <section class="dashboard-stat">
        <span>In attesa</span>
        <strong>{{ $confirmedCount }}</strong>
        <small>confermati</small>
      </section>
      <section class="dashboard-stat">
        <span>Completati</span>
        <strong>{{ $completedCount }}</strong>
        <small>{{ $checkedInCount }} in corso</small>
      </section>
    </div>

    @if ($mode === 'schedule')
      <section class="portal-panel dashboard-filter-panel">
        <div class="section-heading">
          <h2>Nuova disponibilita</h2>
          <span>Uno slot alla volta</span>
        </div>
        <form class="dashboard-filter-grid" method="POST" action="/doctor/availability">
          @csrf
          <label class="form-label">
            Data
            <input class="form-control" type="date" name="date" value="{{ $date }}" required>
          </label>
          <label class="form-label">
            Ora inizio
            <input class="form-control" type="time" name="start_time" value="09:00" required>
          </label>
          <label class="form-label">
            Ora fine
            <input class="form-control" type="time" name="end_time" value="09:30" required>
          </label>
          <label class="form-label">
            ID ambulatorio
            <input class="form-control" inputmode="numeric" name="clinic_id" placeholder="Es. 1" required>
          </label>
          <div class="dashboard-filter-actions">
            <button type="submit" class="btn btn-primary">Aggiungi</button>
          </div>
        </form>
      </section>
    @endif

    <section class="portal-panel dashboard-table-panel">
      <div class="section-heading">
        <h2>{{ $mode === 'schedule' ? 'Agenda completa' : 'Prossimi appuntamenti' }}</h2>
        <span>{{ $visibleAppointments->count() }} mostrati</span>
      </div>

      @if ($visibleAppointments->isNotEmpty())
        <div class="table-responsive dashboard-table-wrap">
          <table class="table dashboard-table align-middle">
            <thead>
              <tr>
                <th>Orario</th>
                <th>Paziente</th>
                <th>Prestazione</th>
                <th>Ambulatorio</th>
                <th>Stato</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($visibleAppointments as $appointment)
                <tr>
                  <td class="dashboard-table__time">{{ $appointment->start_at->format('H:i') }}</td>
                  <td>{{ $appointment->patientName() }}</td>
                  <td>{{ $appointment->service?->name ?? 'Appuntamento' }}</td>
                  <td>{{ $appointment->clinic?->name ?? 'Ambulatorio #'.$appointment->clinic_id }}</td>
                  <td><x-status-badge :status="$appointment->status" /></td>
                  <td>
                    <div class="status-action-group">
                      @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
                        <form method="POST" action="/doctor/appointments/{{ $appointment->id }}/status">@csrf<input type="hidden" name="status" value="checked_in"><button class="btn btn-sm btn-outline-primary">Accetta</button></form>
                        <form method="POST" action="/doctor/appointments/{{ $appointment->id }}/status">@csrf<input type="hidden" name="status" value="no_show"><button class="btn btn-sm btn-outline-danger">Assente</button></form>
                      @elseif ($appointment->status === \App\Models\Appointment::STATUS_CHECKED_IN)
                        <form method="POST" action="/doctor/appointments/{{ $appointment->id }}/status">@csrf<input type="hidden" name="status" value="completed"><button class="btn btn-sm btn-outline-success">Completa</button></form>
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
          <h3>Nessun appuntamento</h3>
          <p>Nessuna visita programmata per questa data.</p>
        </div>
      @endif
    </section>
  </section>
@endsection
