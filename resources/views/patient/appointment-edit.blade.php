@extends('layouts.portal', ['title' => 'Sposta appuntamento - MedPortal'])

@section('content')
  <section class="portal-section booking-wizard-page">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Riprogrammazione</span>
      <h1>Sposta appuntamento</h1>
      <p>
        Stai riprogrammando:
        <strong>{{ $appointment->service?->name ?? 'Appuntamento' }}</strong>
        del {{ $appointment->start_at->format('d/m/Y H:i') }}.
      </p>
    </div>

    @include('patient.partials.booking-wizard', ['isReschedule' => true])
  </section>
@endsection
