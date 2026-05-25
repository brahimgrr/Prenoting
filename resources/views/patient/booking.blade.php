@extends('layouts.portal', ['title' => 'Prenota visita - MedPortal'])

@section('content')
  <section class="portal-section booking-wizard-page">
    <div class="portal-page-heading mb-4">
      <span class="portal-eyebrow">Prenotazione</span>
      <h1>Prenota visita</h1>
    </div>

    @include('patient.partials.booking-wizard', ['isReschedule' => false])
  </section>
@endsection
