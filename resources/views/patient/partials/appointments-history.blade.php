<section class="appointment-group mt-4" data-appointments-history>
  <div class="section-heading d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <h2>Passati e annullati</h2>
    <div class="btn-group flex-wrap" role="group" aria-label="Filtra appuntamenti passati e annullati">
      @foreach ($historyFilters as $filterValue => $filterLabel)
        @php
          $isActiveHistoryFilter = $historyFilter === $filterValue;
          $filterUrl = $filterValue === 'all'
            ? '/patient/appointments'
            : '/patient/appointments?history_filter='.$filterValue;
        @endphp
        <a
          class="btn btn-sm {{ $isActiveHistoryFilter ? 'btn-primary' : 'btn-outline-secondary' }}"
          href="{{ $filterUrl }}"
          data-appointments-history-filter
          @if ($isActiveHistoryFilter) aria-current="page" @endif
        >{{ $filterLabel }}</a>
      @endforeach
    </div>
  </div>
  @forelse ($pastAppointments as $appointment)
    @include('patient.partials.appointment-card', ['appointment' => $appointment, 'manageable' => false, 'muted' => true])
  @empty
    <div class="portal-panel empty-state p-4">
      <h3>Nessuno storico appuntamenti</h3>
      <p>Le visite passate e gli appuntamenti annullati compariranno qui.</p>
    </div>
  @endforelse
</section>
