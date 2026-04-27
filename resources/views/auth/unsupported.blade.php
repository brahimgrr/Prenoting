@extends('layouts.portal', ['title' => 'Ruolo non supportato - MedPortal'])

@section('content')
  <section class="portal-section">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Accesso non disponibile</span>
      <h1>Ruolo non supportato</h1>
      <p>Questo account e autenticato, ma il suo ruolo non puo accedere al portale appuntamenti.</p>
    </div>
    <div class="alert alert-warning" role="alert">
      Ruolo attuale: <strong>{{ auth()->user()?->portalRole() ?? 'mancante' }}</strong>
    </div>
  </section>
@endsection
