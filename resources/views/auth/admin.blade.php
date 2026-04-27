@extends('layouts.portal', ['title' => 'Admin - MedPortal'])

@section('content')
  <section class="portal-section">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Amministrazione</span>
      <h1>Pannello admin</h1>
      <p>La migrazione Laravel mantiene un segnaposto admin. I flussi operativi sono disponibili nei portali paziente, medico e staff.</p>
    </div>
    <div class="portal-panel">
      <h2>Account amministratore</h2>
      <p>Usa questo ruolo per attivita di configurazione future o per estendere il back office.</p>
    </div>
  </section>
@endsection
