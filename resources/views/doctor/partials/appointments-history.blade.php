<section class="appointment-group mt-4" data-appointments-history>
  <div class="section-heading d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h2>Storico appuntamenti</h2>
      <a
        class="btn btn-link px-0 py-1 text-decoration-none fw-semibold"
        href="/doctor/appointments"
        data-appointments-history-hide
      >Nascondi storico appuntamenti</a>
    </div>
    <div class="appointment-history-filter">
      <label class="visually-hidden" for="doctor-history-filter">Filtra storico appuntamenti</label>
      <select
        class="form-select form-select-sm"
        id="doctor-history-filter"
        name="history_filter"
        aria-label="Filtra storico appuntamenti"
        data-appointments-history-filter
      >
      @foreach ($historyFilters as $filterValue => $filterLabel)
        @php
          $isActiveHistoryFilter = $historyFilter === $filterValue;
          $filterUrl = '/doctor/appointments?show_history=1&history_filter='.$filterValue;
        @endphp
        <option value="{{ $filterUrl }}" @selected($isActiveHistoryFilter)>{{ $filterLabel }}</option>
      @endforeach
      </select>
    </div>
  </div>
  @forelse ($pastAppointments as $appointment)
    @include('doctor.partials.appointment-card', ['appointment' => $appointment, 'manageable' => false, 'muted' => true])
  @empty
    <div class="portal-panel empty-state p-4">
      <h3>Nessuno storico appuntamenti</h3>
      <p>Gli appuntamenti passati e annullati compariranno qui.</p>
    </div>
  @endforelse
</section>
