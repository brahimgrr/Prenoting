@extends('layouts.portal', ['title' => 'Riepilogo paziente - MedPortal'])

@section('content')
  @php
    $patientFirstName = auth()->user()?->first_name ?: auth()->user()?->displayName();
    $doctorUser = $primaryDoctor?->user;
    $doctorName = $primaryDoctor?->display_name ?: ($doctorUser?->displayName() ?? 'il medico');
    $doctorInitial = strtoupper(substr($doctorName, 0, 1));
  @endphp

  <section class="portal-section container-xl">
    <div class="portal-page-heading mb-4">
      <div>
        <span class="portal-eyebrow">Portale paziente</span>
        <h1>Benvenuto {{ $patientFirstName }}</h1>
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

    <section class="row g-3 mb-3">
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

    @if ($primaryDoctor)
      <section class="dashboard-doctor-section row g-3 mb-3">
        <div class="col-12">
          <section class="portal-panel doctor-summary-panel p-4">
            <div class="doctor-summary d-flex flex-column flex-lg-row align-items-start justify-content-between gap-4">
              <div class="doctor-summary__identity d-flex align-items-start gap-3">
                <span class="doctor-summary__avatar d-inline-flex align-items-center justify-content-center flex-shrink-0">{{ $doctorInitial }}</span>
                <div class="doctor-summary__body d-grid gap-2">
                  <span class="portal-eyebrow">Il tuo medico</span>
                  <div>
                    <h2 class="mb-1">{{ $doctorName }}</h2>
                    @if ($primaryDoctor->bio)
                      <p class="mb-0">{{ $primaryDoctor->bio }}</p>
                    @else
                      <p class="mb-0">Il riferimento della clinica per visite, controlli e appuntamenti.</p>
                    @endif
                  </div>
                </div>
              </div>

              <dl class="doctor-summary__contacts d-grid gap-3 mb-0">
                @if ($primaryDoctor->clinic_address)
                  <div>
                    <dt>Studio</dt>
                    <dd>{{ $primaryDoctor->clinic_address }}</dd>
                  </div>
                @endif
                @if ($doctorUser?->email)
                  <div class="doctor-summary__contact doctor-summary__contact--email">
                    <dt>Email</dt>
                    <dd>{{ $doctorUser->email }}</dd>
                  </div>
                @endif
                @if ($primaryDoctor->phone)
                  <div>
                    <dt>Telefono</dt>
                    <dd>{{ $primaryDoctor->phone }}</dd>
                  </div>
                @endif
              </dl>

              <a class="btn btn-outline-primary doctor-summary__action" href="/patient/book?mode=service">Prenota visita</a>
            </div>
          </section>
        </div>
      </section>
    @endif
  </section>
@endsection
