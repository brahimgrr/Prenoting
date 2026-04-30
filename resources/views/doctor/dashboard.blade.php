@extends('layouts.portal', ['title' => ($mode === 'schedule' ? 'Agenda' : 'Oggi').' - MedPortal'])

@php
  $confirmedCount = $appointments->where('status', \App\Models\Appointment::STATUS_CONFIRMED)->count();
  $checkedInCount = $appointments->where('status', \App\Models\Appointment::STATUS_CHECKED_IN)->count();
  $completedCount = $appointments->where('status', \App\Models\Appointment::STATUS_COMPLETED)->count();
  $availabilityTotals = $availabilitySlots->flatten(1);
  $availabilityFree = $availabilityTotals->filter(fn ($slot) => ! $slot->is_booked && ! $slot->is_blocked)->count();
  $availabilityBooked = $availabilityTotals->where('is_booked', true)->count();
  $availabilityBlocked = $availabilityTotals->where('is_blocked', true)->count();
  $weekdayOptions = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven'];
  $selectedWeekdays = old('weekdays', $batchForm['weekdays'] ?? [1, 2, 3, 4, 5]);
  $selectedWeekdays = is_array($selectedWeekdays) ? array_map('intval', $selectedWeekdays) : [1, 2, 3, 4, 5];
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
          <h2>Crea disponibilita in batch</h2>
          <span>Imposta una sessione e controlla l'anteprima</span>
        </div>
        <form class="availability-batch-form" method="GET" action="/doctor/availability/preview">
          <label class="form-label">
            Dal
            <input class="form-control" type="date" name="start_date" value="{{ old('start_date', $batchForm['start_date']) }}" required>
          </label>
          <label class="form-label">
            Al
            <input class="form-control" type="date" name="end_date" value="{{ old('end_date', $batchForm['end_date']) }}" required>
          </label>
          <div class="form-label availability-weekday-field">
            Giorni
            <div class="availability-weekday-pills" role="group" aria-label="Giorni della settimana">
              @foreach ($weekdayOptions as $weekdayValue => $weekdayLabel)
                <input
                  class="btn-check"
                  type="checkbox"
                  name="weekdays[]"
                  value="{{ $weekdayValue }}"
                  id="weekday{{ $weekdayValue }}"
                  @checked(in_array($weekdayValue, $selectedWeekdays, true))
                >
                <label class="btn btn-outline-primary" for="weekday{{ $weekdayValue }}">{{ $weekdayLabel }}</label>
              @endforeach
            </div>
          </div>
          <label class="form-label">
            Ora inizio
            <input class="form-control" type="time" name="start_time" value="{{ old('start_time', $batchForm['start_time']) }}" required>
          </label>
          <label class="form-label">
            Ora fine
            <input class="form-control" type="time" name="end_time" value="{{ old('end_time', $batchForm['end_time']) }}" required>
          </label>
          <label class="form-label">
            Durata slot
            <select class="form-select" name="slot_duration" required>
              @foreach ([15, 20, 30, 45, 60] as $duration)
                <option value="{{ $duration }}" @selected((int) old('slot_duration', $batchForm['slot_duration']) === $duration)>{{ $duration }} min</option>
              @endforeach
            </select>
          </label>
          <div class="availability-batch-actions">
            <button type="submit" class="btn btn-primary">Genera anteprima</button>
            <span>Creeremo solo slot futuri e senza sovrapposizioni.</span>
          </div>
        </form>

        @if ($availabilityPreview)
          <div class="availability-preview-card" id="availability-preview">
            <div class="section-heading">
              <div>
                <h2>Anteprima disponibilita</h2>
                <p>{{ $availabilityPreview['input']['start_date'] }} - {{ $availabilityPreview['input']['end_date'] }} · {{ $availabilityPreview['weekdayLabels'] }}</p>
              </div>
              <form method="POST" action="/doctor/availability/batch">
                @csrf
                <input type="hidden" name="start_date" value="{{ $availabilityPreview['input']['start_date'] }}">
                <input type="hidden" name="end_date" value="{{ $availabilityPreview['input']['end_date'] }}">
                @foreach ($availabilityPreview['input']['weekdays'] as $weekday)
                  <input type="hidden" name="weekdays[]" value="{{ $weekday }}">
                @endforeach
                <input type="hidden" name="start_time" value="{{ $availabilityPreview['input']['start_time'] }}">
                <input type="hidden" name="end_time" value="{{ $availabilityPreview['input']['end_time'] }}">
                <input type="hidden" name="slot_duration" value="{{ $availabilityPreview['input']['slot_duration'] }}">
                <button type="submit" class="btn btn-primary" @disabled($availabilityPreview['creatable']->isEmpty())>
                  Crea {{ $availabilityPreview['creatable']->count() }} slot
                </button>
              </form>
            </div>
            <div class="availability-preview-stats">
              <div aria-label="{{ $availabilityPreview['creatable']->count() }} {{ $availabilityPreview['creatable']->count() === 1 ? 'slot creabile' : 'slot creabili' }}">
                <strong>{{ $availabilityPreview['creatable']->count() }}</strong>
                <span>{{ $availabilityPreview['creatable']->count() === 1 ? 'slot creabile' : 'slot creabili' }}</span>
              </div>
              <div aria-label="{{ $availabilityPreview['skipped']->count() }} {{ $availabilityPreview['skipped']->count() === 1 ? 'saltato' : 'saltati' }}">
                <strong>{{ $availabilityPreview['skipped']->count() }}</strong>
                <span>{{ $availabilityPreview['skipped']->count() === 1 ? 'saltato' : 'saltati' }}</span>
              </div>
              <div>
                <strong>{{ $availabilityPreview['input']['slot_duration'] }}'</strong>
                <span>durata slot</span>
              </div>
            </div>
            @if ($availabilityPreview['creatable']->isNotEmpty())
              @php
                $creatablePreview = $availabilityPreview['creatable'];
                $headSlots = $creatablePreview->take(4);
                $tailSlots = $creatablePreview->count() > 8 ? $creatablePreview->slice(-4) : $creatablePreview->slice(4);
              @endphp
              <div class="availability-preview-list">
                @foreach ($headSlots as $candidate)
                  <span>{{ $candidate['start_at']->format('d/m H:i') }} - {{ $candidate['end_at']->format('H:i') }}</span>
                @endforeach
                @if ($creatablePreview->count() > 8)
                  <span>...</span>
                @endif
                @foreach ($tailSlots as $candidate)
                  <span>{{ $candidate['start_at']->format('d/m H:i') }} - {{ $candidate['end_at']->format('H:i') }}</span>
                @endforeach
              </div>
            @else
              <p class="mb-0 text-muted">Nessuno slot creabile con questi parametri. Cambia giorni, orari o intervallo.</p>
            @endif
            @if ($availabilityPreview['skipped']->isNotEmpty())
              <details class="availability-preview-skipped">
                <summary>Mostra slot saltati</summary>
                <div>
                  @foreach ($availabilityPreview['skipped']->take(6) as $candidate)
                    <span>{{ $candidate['start_at']->format('d/m H:i') }} - {{ $candidate['end_at']->format('H:i') }} · {{ $candidate['reason'] }}</span>
                  @endforeach
                </div>
              </details>
            @endif
          </div>
        @endif
      </section>

      <section class="portal-panel dashboard-filter-panel">
        <div class="section-heading">
          <h2>Disponibilita future</h2>
          <span>{{ $availabilityTotals->count() }} slot · {{ $availabilityFree }} liberi · {{ $availabilityBooked }} prenotati · {{ $availabilityBlocked }} bloccati</span>
        </div>
        @if ($availabilitySlots->isNotEmpty())
          <div class="availability-day-list">
            @foreach ($availabilitySlots as $slotDate => $slotsForDay)
              <details class="availability-day">
                <summary class="availability-day__summary">
                  <span>{{ \Carbon\CarbonImmutable::parse($slotDate)->format('d/m/Y') }}</span>
                  <small>{{ $slotsForDay->count() }} {{ $slotsForDay->count() === 1 ? 'slot' : 'slot' }}</small>
                </summary>
                <div class="availability-slot-list">
                  @foreach ($slotsForDay as $slot)
                    <article class="availability-slot-row">
                      <div>
                        <strong>{{ $slot->start_at->format('H:i') }} - {{ $slot->end_at->format('H:i') }}</strong>
                      </div>
                      <div class="availability-slot-row__actions">
                        @if ($slot->is_booked)
                          <span class="badge text-bg-secondary">Prenotato</span>
                        @elseif ($slot->is_blocked)
                          <span class="badge text-bg-warning">Bloccato</span>
                          <form method="POST" action="/doctor/availability/{{ $slot->id }}/unblock">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Riapri</button>
                          </form>
                        @else
                          <span class="badge text-bg-success">Libero</span>
                          <form method="POST" action="/doctor/availability/{{ $slot->id }}/block">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Blocca</button>
                          </form>
                        @endif
                      </div>
                    </article>
                  @endforeach
                </div>
              </details>
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
                <th>Stato</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($visibleAppointments as $appointment)
                <tr>
                  <td class="dashboard-table__time">{{ $appointment->start_at->format('H:i') }}</td>
                  <td>{{ $appointment->patientName() }}</td>
                  <td>{{ $appointment->service?->name ?? 'Appuntamento' }}</td>
                  <td><x-status-badge :status="$appointment->status" /></td>
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
