@extends('layouts.portal', ['title' => 'Appuntamenti - MedPortal'])

@section('content')
  <section class="portal-section container-xl">
    <div class="portal-page-heading mb-4">
      <span class="portal-eyebrow">Appuntamenti</span>
      <h1>Appuntamenti</h1>
    </div>

    <section class="appointment-group">
      <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2>Imminenti</h2>
      </div>
      @forelse ($upcomingAppointments as $appointment)
        @include('doctor.partials.appointment-card', ['appointment' => $appointment, 'manageable' => true])
      @empty
        <div class="portal-panel empty-state p-4">
          <h3>Nessun appuntamento imminente</h3>
          <p>Gli appuntamenti futuri prenotati compariranno qui.</p>
        </div>
      @endforelse
    </section>

    @if ($showHistory)
      @include('doctor.partials.appointments-history')
    @else
      @include('doctor.partials.appointments-history-placeholder')
    @endif
    @include('doctor.partials.appointments-history-interactions')
  </section>
@endsection
