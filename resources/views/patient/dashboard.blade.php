@extends('layouts.portal', ['title' => 'Riepilogo paziente - MedPortal'])

@section('content')
  <section class="portal-section">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale paziente</span>
        <h1>Riepilogo</h1>
      </div>
      <a class="btn btn-primary" href="/patient/book">Prenota visita</a>
    </div>

    <div class="dashboard-grid">
      <section class="metric-panel">
        <span class="metric-panel__label">Prossimi</span>
        <strong>{{ $upcomingAppointments->count() }}</strong>
        <span class="metric-panel__caption">visite confermate</span>
      </section>

      <section class="portal-panel next-appointment-panel">
        <div class="section-heading">
          <h2>Prossimo appuntamento</h2>
        </div>
        @if ($nextAppointment)
          <div class="next-appointment">
            <div>
              <h3>{{ $nextAppointment->service?->name ?? 'Appuntamento' }}</h3>
              <p>{{ $nextAppointment->start_at->format('d/m/Y H:i') }}</p>
            </div>
            <dl>
              <div>
                <dt>Medico</dt>
                <dd>{{ $nextAppointment->doctor?->display_name ?? 'Medico in attesa' }}</dd>
              </div>
              <div>
                <dt>Ambulatorio</dt>
                <dd>{{ $nextAppointment->clinic?->name ?? 'Ambulatorio in attesa' }}</dd>
              </div>
            </dl>
            <x-status-badge :status="$nextAppointment->status" />
          </div>
        @else
          <div class="empty-state">
            <h3>Nessun appuntamento imminente</h3>
            <p>La tua prossima visita confermata comparira qui.</p>
          </div>
        @endif
      </section>
    </div>

    <section class="quick-actions" aria-label="Azioni rapide">
      <a class="quick-action" href="/patient/book?mode=service">
        <span>Prenota per prestazione</span>
        <strong>Trova assistenza</strong>
      </a>
      <a class="quick-action" href="/patient/book?mode=doctor">
        <span>Prenota per medico</span>
        <strong>Scegli professionista</strong>
      </a>
      <a class="quick-action" href="/patient/appointments">
        <span>I miei appuntamenti</span>
        <strong>Gestisci visite</strong>
      </a>
    </section>

    <section class="portal-panel">
      <div class="section-heading">
        <h2>Appuntamenti imminenti</h2>
        <a href="/patient/appointments">Vedi tutti</a>
      </div>
      @if ($upcomingAppointments->isNotEmpty())
        <div class="appointment-list compact">
          @foreach ($upcomingAppointments->take(4) as $appointment)
            <article class="appointment-row">
              <div>
                <h3>{{ $appointment->service?->name ?? 'Appuntamento' }}</h3>
                <p>{{ $appointment->start_at->format('d/m/Y H:i') }}</p>
              </div>
              <div class="appointment-row__meta">
                <span>{{ $appointment->doctor?->display_name ?? 'Medico in attesa' }}</span>
                <x-status-badge :status="$appointment->status" />
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessuna visita programmata</h3>
          <p>Prenota un appuntamento quando sei pronto.</p>
        </div>
      @endif
    </section>
  </section>
@endsection
