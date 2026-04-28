@extends('layouts.portal', ['title' => 'Prenota visita - MedPortal'])

@section('content')
  <section class="portal-section booking-wizard-page">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Prenotazione</span>
      <h1>Prenota visita</h1>
      <p>Scegli prestazione, giorno e orario: prima dell'invio vedrai un riepilogo da confermare.</p>
    </div>

    @include('patient.partials.booking-wizard', ['isReschedule' => false])
  </section>
@endsection
