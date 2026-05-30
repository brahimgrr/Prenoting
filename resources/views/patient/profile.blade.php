@extends('layouts.portal', ['title' => 'Profilo paziente - MedPortal'])

@php
  $user = auth()->user();
  $profile = $profile ?? $user->patientProfile;
@endphp

@section('content')
  <section class="portal-section profile-page container-xl">
    <div class="portal-page-heading mb-4">
      <span class="portal-eyebrow">Profilo</span>
      <h1>Profilo paziente</h1>
    </div>

    <div class="profile-stack d-grid gap-3">
      <section class="portal-panel profile-readonly-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Dati anagrafici</h2>
        </div>
        <div class="profile-data-grid row g-3">
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Nome</span>
              <strong class="d-block mt-1">{{ $user->first_name ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Cognome</span>
              <strong class="d-block mt-1">{{ $user->last_name ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Sesso</span>
              <strong class="d-block mt-1">{{ match($profile?->gender) { 'M' => 'Maschile', 'F' => 'Femminile', default => '-' } }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Data di nascita</span>
              <strong class="d-block mt-1">{{ $profile?->date_of_birth?->format('d/m/Y') ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Luogo di nascita</span>
              <strong class="d-block mt-1">{{ $profile?->place_of_birth ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Codice fiscale</span>
              <strong class="d-block mt-1">{{ $profile?->codice_fiscale ?: '-' }}</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="portal-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Contatto</h2>
        </div>
        <form method="POST" action="/patient/profile" class="profile-form d-grid gap-3">
          @csrf
          @method('PATCH')
          <div class="profile-field-list d-grid gap-3">
            <label class="form-label d-grid gap-2 mb-0">
              Email
              <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email">
              @error('email') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label d-grid gap-2 mb-0">
              Telefono
              <input class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $profile?->phone) }}" required>
              @error('phone') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
          </div>
          <label class="form-label d-grid gap-2 mb-0">
            Indirizzo
            <input class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $profile?->address) }}">
            @error('address') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
          </label>
          <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </form>
      </section>

      <section class="portal-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Password</h2>
        </div>
        <form method="POST" action="/patient/password" class="profile-form d-grid gap-3">
          @csrf
          @method('PUT')
          <div class="profile-field-list d-grid gap-3">
            <label class="form-label d-grid gap-2 mb-0">
              Password attuale
              <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" autocomplete="current-password" required>
              @error('current_password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label d-grid gap-2 mb-0">
              Nuova password
              <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" autocomplete="new-password" required>
              @error('password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label d-grid gap-2 mb-0">
              Conferma nuova password
              <input class="form-control" type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Aggiorna password</button>
        </form>
      </section>
    </div>
  </section>
@endsection
