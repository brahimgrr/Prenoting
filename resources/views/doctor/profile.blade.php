@extends('layouts.portal', ['title' => 'Profilo medico - MedPortal'])

@php
  $user = auth()->user();
  $profile = $doctorProfile ?? $user->doctorProfile;
@endphp

@section('content')
  <section class="portal-section profile-page">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Profilo</span>
      <h1>Profilo medico</h1>
    </div>

    <div class="profile-stack">
      <section class="portal-panel profile-readonly-panel">
        <div class="section-heading">
          <h2>Dati professionali</h2>
        </div>
        <div class="profile-data-grid">
          <div class="profile-data-tile">
            <span>Nome visualizzato</span>
            <strong>{{ $profile?->display_name ?: '-' }}</strong>
          </div>
          <div class="profile-data-tile">
            <span>Numero di iscrizione all'albo</span>
            <strong>{{ $profile?->license_number ?: '-' }}</strong>
          </div>
        </div>
      </section>

      <section class="portal-panel">
        <div class="section-heading">
          <h2>Contatti ambulatorio</h2>
        </div>
        <form method="POST" action="/doctor/profile" class="profile-form">
          @csrf
          @method('PATCH')
          <div class="profile-grid">
            <label class="form-label">
              Email
              <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email">
              @error('email') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label">
              Telefono
              <input class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $profile?->phone) }}">
              @error('phone') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
          </div>
          <label class="form-label">
            Luogo ambulatorio
            <input class="form-control @error('clinic_address') is-invalid @enderror" name="clinic_address" value="{{ old('clinic_address', $profile?->clinic_address) }}" required>
            @error('clinic_address') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
          </label>
          <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </form>
      </section>
    </div>
  </section>
@endsection
