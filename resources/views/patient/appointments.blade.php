@extends('layouts.portal', ['title' => 'I miei appuntamenti - MedPortal'])

@section('content')
  <section class="portal-section container-xl">
    <div class="portal-page-heading mb-4">
      <span class="portal-eyebrow">Appuntamenti</span>
      <h1>I miei appuntamenti</h1>
    </div>

    <section class="appointment-group">
      <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2>Imminenti</h2>
      </div>
      @forelse ($upcomingAppointments as $appointment)
        @include('patient.partials.appointment-card', ['appointment' => $appointment, 'manageable' => $appointment->isFutureConfirmed()])
      @empty
        <div class="portal-panel empty-state p-4">
          <h3>Nessun appuntamento imminente</h3>
          <p>Le visite future confermate compariranno qui.</p>
          <a class="btn btn-primary empty-state__action d-inline-flex mt-3" href="/patient/book">Prenota visita</a>
        </div>
      @endforelse
    </section>

    @include('patient.partials.appointments-history')
    @include('patient.partials.appointments-history-interactions')
  </section>
@endsection
