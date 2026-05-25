@extends('layouts.portal', ['title' => 'Riepilogo paziente - MedPortal'])

@section('content')
  <section class="portal-section container-xl">
    <div class="portal-page-heading mb-4">
      <div>
        <span class="portal-eyebrow">Portale paziente</span>
        <h1>Riepilogo</h1>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <section class="metric-panel h-100 d-grid p-4">
          <span class="metric-panel__label">Prossimi</span>
          <strong class="align-self-center">{{ $upcomingAppointments->count() }}</strong>
          <span class="metric-panel__caption">visite confermate</span>
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
                <p>{{ $nextAppointment->start_at->format('d/m/Y H:i') }} - {{ $nextAppointment->end_at->format('H:i') }}</p>
              </div>
            </div>
          @else
            <div class="empty-state">
              <h3>Nessun appuntamento imminente</h3>
              <p>La tua prossima visita confermata comparira qui.</p>
            </div>
          @endif
        </section>
      </div>
    </div>

    <section class="row g-3 mb-3" aria-label="Azioni rapide">
      <div class="col-12 col-md-6">
        <a class="quick-action d-grid gap-2 h-100 p-4" href="/patient/book?mode=service">
          <span>Prenota visita</span>
          <strong>Trova assistenza</strong>
        </a>
      </div>
      <div class="col-12 col-md-6">
        <a class="quick-action d-grid gap-2 h-100 p-4" href="/patient/appointments">
          <span>I miei appuntamenti</span>
          <strong>Gestisci visite</strong>
        </a>
      </div>
    </section>
  </section>
@endsection
