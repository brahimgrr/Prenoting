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

      <section class="portal-panel dashboard-filter-panel">
        <div class="section-heading">
          <h2>Disponibilita future</h2>
          <span>{{ $availabilitySlots->flatten(1)->count() }} slot</span>
        </div>
        @if ($availabilitySlots->isNotEmpty())
          <div class="availability-day-list">
            @foreach ($availabilitySlots as $slotDate => $slotsForDay)
              <div class="availability-day">
                <h3>{{ \Carbon\CarbonImmutable::parse($slotDate)->format('d/m/Y') }}</h3>
                <div class="availability-slot-list">
                  @foreach ($slotsForDay as $slot)
                    <article class="availability-slot-row">
                      <div>
                        <strong>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</strong>
                        <span>{{ $slot->clinic?->name ?? 'Ambulatorio #'.$slot->clinic_id }}</span>
                      </div>
                      <div class="availability-slot-row__actions">
                        @if ($slot->is_booked)
                          <span class="badge text-bg-secondary">Prenotato</span>
                        @elseif ($slot->is_blocked)
                          <span class="badge text-bg-warning">Bloccato</span>
                          <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#unblockSlotModal{{ $slot->id }}">Riapri</button>
                        @else
                          <span class="badge text-bg-success">Libero</span>
                          <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#blockSlotModal{{ $slot->id }}">Blocca</button>
                        @endif
                      </div>
                    </article>

                    <div class="modal fade" id="blockSlotModal{{ $slot->id }}" tabindex="-1" aria-labelledby="blockSlotModal{{ $slot->id }}Label" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h2 class="modal-title fs-5" id="blockSlotModal{{ $slot->id }}Label">Blocca disponibilita</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                          </div>
                          <div class="modal-body">
                            Bloccare lo slot del {{ $slot->start_at->format('d/m/Y H:i') }} lo nascondera ai pazienti.
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Torna indietro</button>
                            <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                              @csrf
                              <button type="submit" class="btn btn-danger">Blocca slot</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal fade" id="unblockSlotModal{{ $slot->id }}" tabindex="-1" aria-labelledby="unblockSlotModal{{ $slot->id }}Label" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h2 class="modal-title fs-5" id="unblockSlotModal{{ $slot->id }}Label">Riapri disponibilita</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                          </div>
                          <div class="modal-body">
                            Riaprire lo slot del {{ $slot->start_at->format('d/m/Y H:i') }} lo rendera nuovamente prenotabile.
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Torna indietro</button>
                            <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                              @csrf
                              <button type="submit" class="btn btn-primary">Riapri slot</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="empty-state">
            <h3>Nessuna disponibilita futura</h3>
            <p>Aggiungi nuovi slot dal modulo qui sopra.</p>
          </div>
        @endif
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
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#noShowAppointmentModal{{ $appointment->id }}">Assente</button>
                        <div class="modal fade" id="noShowAppointmentModal{{ $appointment->id }}" tabindex="-1" aria-labelledby="noShowAppointmentModal{{ $appointment->id }}Label" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h2 class="modal-title fs-5" id="noShowAppointmentModal{{ $appointment->id }}Label">Segna paziente assente</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                              </div>
                              <div class="modal-body">
                                Confermi che {{ $appointment->patientName() }} non si e presentato per {{ $appointment->service?->name ?? 'questo appuntamento' }}?
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Torna indietro</button>
                                <form method="POST" action="/doctor/appointments/{{ $appointment->id }}/status">
                                  @csrf
                                  <input type="hidden" name="status" value="no_show">
                                  <button type="submit" class="btn btn-danger">Conferma assenza</button>
                                </form>
                              </div>
                            </div>
                          </div>
                        </div>
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
