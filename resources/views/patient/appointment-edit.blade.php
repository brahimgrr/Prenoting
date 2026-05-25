@extends('layouts.portal', ['title' => 'Sposta appuntamento - MedPortal'])

@section('content')
  @include('patient.partials.booking-page', [
    'eyebrow' => 'Riprogrammazione',
    'heading' => 'Sposta appuntamento',
    'appointment' => $appointment,
  ])
@endsection
