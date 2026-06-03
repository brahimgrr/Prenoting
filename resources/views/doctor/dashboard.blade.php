@extends('layouts.portal', ['title' => 'Riepilogo medico - MedPortal'])

@section('content')
  @php
    $doctorProfile = auth()->user()?->doctorProfile;
    $doctorName = $doctorProfile?->display_name ?: auth()->user()?->displayName();
  @endphp

  <section class="portal-section container-xl">
    <div class="portal-page-heading mb-4">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Benvenuto {{ $doctorName }}</h1>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <section class="metric-panel h-100 d-grid p-4">
          <span class="metric-panel__label">Prossimi appuntamenti</span>
          <strong class="align-self-center">{{ $upcomingAppointments->count() }}</strong>
          <span class="metric-panel__caption">appuntamenti prenotati</span>
        </section>
      </div>

      <div class="col-12 col-md-8">
        <section class="portal-panel next-appointment-panel h-100 p-4">
          <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2>Prossimo appuntamento</h2>
          </div>
          @if ($nextAppointment)
            <div class="next-appointment d-grid gap-2">
              <div>
                <h3>{{ $nextAppointment->service?->name ?? 'Appuntamento' }}</h3>
                <p>{{ $nextAppointment->patientName() }}</p>
                <p>{{ $nextAppointment->start_at->format('d/m/Y H:i') }} - {{ $nextAppointment->end_at->format('H:i') }}</p>
              </div>
            </div>
          @else
            <div class="empty-state">
              <h3>Nessun appuntamento imminente</h3>
              <p>Il prossimo appuntamento prenotato comparira qui.</p>
            </div>
          @endif
        </section>
      </div>
    </div>

    <section class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <a class="quick-action d-grid gap-2 h-100 p-4" href="/doctor/agenda">
          <span>Disponibilita</span>
          <strong>Gestisci Agenda</strong>
        </a>
      </div>
      <div class="col-12 col-md-4">
        <a class="quick-action d-grid gap-2 h-100 p-4" href="/doctor/appointments">
          <span>Prenotazioni</span>
          <strong>Gestisci appuntamenti</strong>
        </a>
      </div>
      <div class="col-12 col-md-4">
        <a class="quick-action d-grid gap-2 h-100 p-4" href="/doctor/treatments">
          <span>Catalogo</span>
          <strong>Gestisci Trattamenti</strong>
        </a>
      </div>
    </section>
  </section>
@endsection
