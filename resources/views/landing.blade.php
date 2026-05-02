@extends('layouts.guest', ['title' => 'Studio Dermatologico — MedPortal'])

@section('content')
@php
  $doctorName = $doctor?->display_name ?? 'Dott. Mbappe';
  $doctorClinicAddress = $doctor?->clinic_address ?: 'Via Roma 1';
  $doctorPhone = $doctor?->phone ?: '555-1000';
  $doctorPhoneHref = preg_replace('/[^\d+]/', '', $doctorPhone);
  $doctorEmail = $doctor?->user?->email ?: 'doctor.derm@example.com';
@endphp

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
  </nav>

  <main class="landing-main">
    <section class="landing-hero" aria-labelledby="landing-title">
      <div class="landing-hero__media" aria-hidden="{{ file_exists(public_path('images/pigeon.png')) ? 'false' : 'true' }}">
        @if (file_exists(public_path('images/pigeon.png')))
          <img class="landing-hero__portrait" src="/images/pigeon.png" alt="Foto {{ $doctorName }}">
        @else
          <div class="landing-hero__portrait landing-hero__portrait--placeholder">
            {{ strtoupper(substr($doctorName, 0, 1)) }}
          </div>
        @endif
      </div>

      <div class="landing-hero__content">
        <span class="landing-kicker">Studio Dermatologico</span>
        <h1 id="landing-title">{{ $doctorName }}</h1>
        <p class="landing-specialty">Specialista in Dermatologia</p>
        <p class="landing-intro">
          Cura della pelle, controlli dermatologici e gestione degli appuntamenti in un unico portale semplice e riservato.
        </p>

        <div class="landing-cta" aria-label="Azioni principali">
          <a class="btn btn-primary btn-lg" href="/login">Accedi Area Personale</a>
          <a class="btn btn-outline-primary btn-lg" href="/register">Nuovo Paziente</a>
        </div>

        <div class="landing-contacts" aria-label="Contatti ambulatorio">
          <div>
            <span>Indirizzo</span>
            <strong>{{ $doctorClinicAddress }}</strong>
          </div>
          <div>
            <span>Telefono</span>
            <a href="tel:{{ $doctorPhoneHref }}">{{ $doctorPhone }}</a>
          </div>
          <div>
            <span>Email</span>
            <a href="mailto:{{ $doctorEmail }}">{{ $doctorEmail }}</a>
          </div>
        </div>

        <div class="landing-note">
          <span>Dermatologia clinica</span>
          <span>Visite su appuntamento</span>
        </div>
      </div>
    </section>
  </main>

</div>
@endsection
