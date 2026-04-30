@extends('layouts.portal', ['title' => 'Admin - MedPortal'])

@section('content')
  <section class="portal-section operations-dashboard operations-dashboard--wide">
    <div class="portal-page-heading">
      <div>
        <span class="portal-eyebrow">Portale admin</span>
        <h1>Admin</h1>
        <p>Accedi agli strumenti di amministrazione mantenendo disponibile il logout del portale.</p>
      </div>
    </div>

    <section class="portal-panel">
      <div class="section-heading">
        <h2>Database</h2>
        <span>Console phpMyAdmin</span>
      </div>
      <p>Apri phpMyAdmin in una nuova scheda quando devi consultare o gestire il database.</p>
      <a class="btn btn-primary" href="{{ $phpMyAdminUrl }}" target="_blank" rel="noopener">
        Apri phpMyAdmin
      </a>
    </section>
  </section>
@endsection
