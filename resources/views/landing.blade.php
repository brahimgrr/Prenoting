@extends('layouts.guest', ['title' => 'Studio Dermatologico — MedPortal'])

@section('content')
<div class="landing-page">

  {{-- Navbar --}}
  <nav class="landing-nav">
    <a class="landing-brand" href="{{ route('home') }}">
      <span class="app-brand__mark">M</span>
      <span>
        <span class="landing-brand__name">MedPortal</span>
        <span class="landing-brand__sub">Studio Dermatologico</span>
      </span>
    </a>
    <div class="landing-nav__links">
      <a class="landing-nav__link landing-nav__link--ghost" href="/login">Accedi</a>
      <a class="landing-nav__link landing-nav__link--primary" href="/register">Nuovo Paziente</a>
    </div>
  </nav>

  {{-- Hero --}}
  <section class="landing-hero">
    <span class="portal-eyebrow">Studio Dermatologico</span>
    <h1>La tua pelle,<br>in buone mani</h1>
    <p>Prenota una visita dermatologica, gestisci i tuoi appuntamenti e accedi alla tua area personale.</p>
    <div class="landing-cta">
      <a class="btn btn-primary btn-lg" href="/login">Accedi Area Personale</a>
      <a class="btn btn-outline-primary btn-lg" href="/register">Nuovo Paziente</a>
    </div>
  </section>

  {{-- Features --}}
  <section class="landing-features">
    <div class="landing-card">
      <span class="landing-card__icon">🔬</span>
      <h3>Visite Dermatologiche</h3>
      <p>Diagnosi e controllo della pelle con specialisti qualificati.</p>
    </div>
    <div class="landing-card">
      <span class="landing-card__icon">🧴</span>
      <h3>Trattamenti Estetici</h3>
      <p>Cura e benessere della pelle con trattamenti mirati.</p>
    </div>
    <div class="landing-card">
      <span class="landing-card__icon">📋</span>
      <h3>Mappatura Nei</h3>
      <p>Screening e prevenzione con tecnologia avanzata.</p>
    </div>
  </section>

  {{-- Footer --}}
  <footer class="landing-footer">
    &copy; {{ date('Y') }} MedPortal &mdash; Studio Dermatologico &mdash; Tutti i diritti riservati
  </footer>

</div>
@endsection
