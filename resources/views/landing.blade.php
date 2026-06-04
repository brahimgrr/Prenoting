@extends('layouts.guest', ['title' => 'MedPortal - Studio Dermatologico'])

@section('content')
  <header class="sticky-top bg-white border-bottom">
    <div class="container-xxl d-flex align-items-center justify-content-between py-3">
      <div class="d-flex align-items-center gap-2">
        <span class="app-brand__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">M</span>
        <span class="app-brand__name">MedPortal</span>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/login">Accedi</a>
        <a class="btn btn-primary" href="/register">Registrati</a>
      </div>
    </div>
  </header>

  <main>
    <section class="landing-hero text-center py-5">
      <div class="container-xxl py-md-5">
        <span class="landing-eyebrow">Studio Dermatologico</span>
        <h1 class="landing-hero__title mx-auto mt-2 mb-3">La salute della tua pelle, a portata di click</h1>
        <p class="landing-hero__lead text-muted mx-auto mb-4">
          Prenota visite ed esami dermatologici online in pochi minuti. Scegli il trattamento,
          trova l'orario che preferisci e gestisci i tuoi appuntamenti dal portale.
        </p>
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 gap-sm-3">
          <a class="btn btn-primary btn-lg" href="/register">Registrati e prenota</a>
          <a class="btn btn-outline-secondary btn-lg" href="/login">Sei gi&agrave; paziente? Accedi</a>
        </div>
      </div>
    </section>

    <section class="py-5" id="trattamenti">
      <div class="container-xxl">
        <div class="text-center mb-4">
          <span class="landing-eyebrow">I nostri trattamenti</span>
          <h2 class="landing-section-title mt-2 mb-2">Visite ed esami disponibili</h2>
          <p class="text-muted mb-0">Tutte le prestazioni prenotabili online dal portale.</p>
        </div>
        <div class="row g-3">
          @foreach ($services as $service)
            <div class="col-12 col-md-6 col-lg-4">
              <div class="landing-treatment d-flex align-items-center gap-3 p-3 h-100">
                <span class="landing-treatment__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">+</span>
                <span>{{ $service->name }}</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="landing-steps py-5">
      <div class="container-xxl">
        <div class="text-center mb-4">
          <span class="landing-eyebrow">Come funziona</span>
          <h2 class="landing-section-title mt-2">Prenota in tre semplici passi</h2>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <div class="landing-step p-4 h-100">
              <span class="landing-step__num d-inline-flex align-items-center justify-content-center mb-3">1</span>
              <h3 class="landing-step__title mb-2">Crea il tuo account</h3>
              <p class="text-muted mb-0">Registrati gratuitamente come paziente in meno di un minuto.</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="landing-step p-4 h-100">
              <span class="landing-step__num d-inline-flex align-items-center justify-content-center mb-3">2</span>
              <h3 class="landing-step__title mb-2">Scegli il trattamento</h3>
              <p class="text-muted mb-0">Seleziona la visita o l'esame di cui hai bisogno dal catalogo.</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="landing-step p-4 h-100">
              <span class="landing-step__num d-inline-flex align-items-center justify-content-center mb-3">3</span>
              <h3 class="landing-step__title mb-2">Prenota l'appuntamento</h3>
              <p class="text-muted mb-0">Trova la disponibilit&agrave; che preferisci e conferma la prenotazione online.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-cta text-center py-5">
      <div class="container-xxl">
        <h2 class="landing-cta__title mb-2">Pronto a prenderti cura della tua pelle?</h2>
        <p class="landing-cta__text mb-4">Registrati ora e prenota il tuo primo appuntamento.</p>
        <a class="btn btn-primary btn-lg" href="/register">Inizia ora</a>
      </div>
    </section>
  </main>

  <footer class="border-top">
    <div class="container-xxl d-flex align-items-center justify-content-center gap-2 py-4">
      <span class="app-brand__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">M</span>
      <span class="app-brand__name">MedPortal</span>
    </div>
  </footer>
@endsection
