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
  <section class="landing-services">
    <div class="landing-services__heading">
      <div>
        <span class="portal-eyebrow">Trattamenti</span>
        <h2>Prestazioni disponibili</h2>
      </div>
    </div>

    <div class="landing-services__carousel{{ $services->count() <= 3 ? ' landing-services__carousel--centered' : '' }}">
      @if ($services->count() > 3)
        <button class="landing-services__arrow landing-services__arrow--prev" type="button" data-landing-services-prev aria-label="Trattamenti precedenti">‹</button>
      @endif
      <div class="landing-features{{ $services->count() <= 3 ? ' landing-features--centered' : '' }}" data-landing-services-track>
        @forelse ($services as $service)
          <div class="landing-card">
            <span class="landing-card__icon">{{ $service->category === \App\Models\MedicalService::CATEGORY_EXAM ? '🔬' : '🧴' }}</span>
            <h3>{{ $service->name }}</h3>
            <p>{{ $service->category === \App\Models\MedicalService::CATEGORY_EXAM ? 'Esame dermatologico' : 'Visita dermatologica' }} · {{ $service->duration_minutes }} min</p>
          </div>
        @empty
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
        @endforelse
      </div>
      @if ($services->count() > 3)
        <button class="landing-services__arrow landing-services__arrow--next" type="button" data-landing-services-next aria-label="Trattamenti successivi">›</button>
      @endif
    </div>
  </section>

  {{-- Medico --}}
  <section class="landing-doctor">
    <span class="landing-doctor__eyebrow">Chi ti segue</span>
    <div class="landing-doctor__wrap">
      <div class="landing-doctor__card">
        @php
          $doctorName = $doctor?->display_name ?? 'Dott. Mbappe';
          $doctorClinicAddress = $doctor?->clinic_address ?: 'Via Roma 1';
          $doctorPhone = $doctor?->phone ?: '555-1000';
          $doctorEmail = $doctor?->user?->email ?: 'doctor.derm@example.com';
        @endphp
        @if (file_exists(public_path('images/general.png')))
          <img class="landing-doctor__photo" src="/images/general.png" alt="Foto {{ $doctorName }}">
        @else
          <div class="landing-doctor__photo landing-doctor__photo--placeholder">
            {{ strtoupper(substr($doctorName, 0, 1)) }}
          </div>
        @endif
        <div>
          <p class="landing-doctor__name">{{ $doctorName }}</p>
          <p class="landing-doctor__title">Specialista in Dermatologia</p>
        </div>
        <div class="landing-doctor__divider"></div>
        <div class="landing-doctor__clinic">
          <span>🏥 <span>{{ $doctorClinicAddress }}</span></span>
          <span>📞 <span>{{ $doctorPhone }}</span></span>
          <span>✉️ <span>{{ $doctorEmail }}</span></span>
        </div>
      </div>
    </div>
  </section>

  {{-- Footer --}}
  <footer class="landing-footer">
    &copy; {{ date('Y') }} MedPortal &mdash; Studio Dermatologico &mdash; Tutti i diritti riservati
  </footer>

</div>
@endsection
