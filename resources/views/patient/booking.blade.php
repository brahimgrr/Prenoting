@extends('layouts.portal', ['title' => 'Prenota visita - MedPortal'])

@section('content')
  @include('patient.partials.booking-page', [
    'eyebrow' => 'Prenotazione',
    'heading' => 'Prenota visita',
  ])
@endsection
