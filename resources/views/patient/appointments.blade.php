@extends('layouts.portal', ['title' => 'I miei appuntamenti - MedPortal'])

@section('content')
  <section class="portal-section">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Appuntamenti</span>
      <h1>I miei appuntamenti</h1>
    </div>

    <section class="appointment-group">
      <div class="section-heading">
        <h2>Imminenti</h2>
      </div>
      @forelse ($upcomingAppointments as $appointment)
        @include('patient.partials.appointment-card', ['appointment' => $appointment, 'manageable' => $appointment->isFutureConfirmed()])
      @empty
        <div class="portal-panel empty-state">
          <h3>Nessun appuntamento imminente</h3>
          <p>Le visite future confermate compariranno qui.</p>
          <a class="btn btn-primary empty-state__action" href="/patient/book">Prenota visita</a>
        </div>
      @endforelse
    </section>

    <section class="appointment-group">
      <div class="section-heading">
        <h2>Passati e annullati</h2>
      </div>
      @forelse ($pastAppointments as $appointment)
        @include('patient.partials.appointment-card', ['appointment' => $appointment, 'manageable' => false, 'muted' => true])
      @empty
        <div class="portal-panel empty-state">
          <h3>Nessuno storico appuntamenti</h3>
          <p>Le visite passate e gli appuntamenti annullati compariranno qui.</p>
        </div>
      @endforelse
    </section>
  </section>
@endsection
