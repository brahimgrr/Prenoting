@extends('layouts.portal', ['title' => 'Profilo paziente - MedPortal'])

@php
  $user = auth()->user();
  $profile = $profile ?? $user->patientProfile;
@endphp

@section('content')
  <section class="portal-section profile-page">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Profilo</span>
      <h1>Profilo paziente</h1>
      <p>Gestisci contatti e password. I dati anagrafici restano protetti.</p>
    </div>

    <div class="profile-stack">
      <section class="portal-panel profile-readonly-panel">
        <div class="section-heading">
          <h2>Dati anagrafici</h2>
          <span>Dati non modificabili</span>
        </div>
        <div class="profile-grid">
          <label class="form-label">
            Nome
            <input class="form-control" value="{{ $user->first_name ?: '-' }}" readonly>
          </label>
          <label class="form-label">
            Cognome
            <input class="form-control" value="{{ $user->last_name ?: '-' }}" readonly>
          </label>
          <label class="form-label">
            Sesso
            <input class="form-control" value="{{ match($profile?->gender) { 'M' => 'Maschile', 'F' => 'Femminile', default => '-' } }}" readonly>
          </label>
          <label class="form-label">
            Data di nascita
            <input class="form-control" value="{{ $profile?->date_of_birth?->format('d/m/Y') ?: '-' }}" readonly>
          </label>
          <label class="form-label">
            Luogo di nascita
            <input class="form-control" value="{{ $profile?->place_of_birth ?: '-' }}" readonly>
          </label>
          <label class="form-label">
            Codice fiscale
            <input class="form-control" value="{{ $profile?->codice_fiscale ?: '-' }}" readonly>
          </label>
          <label class="form-label">
            Codice identificativo
            <input class="form-control" value="{{ $profile?->identity_code ?: '-' }}" readonly>
          </label>
        </div>
        <p class="profile-lock-note">Questi dati non sono modificabili dal portale.</p>
      </section>

      <section class="portal-panel">
        <div class="section-heading">
          <h2>Contatto</h2>
          <span>Email, telefono e indirizzo</span>
        </div>
        <form method="POST" action="/patient/profile" class="profile-form">
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
              <input class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $profile?->phone) }}" required>
              @error('phone') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
          </div>
          <label class="form-label">
            Indirizzo
            <input class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $profile?->address) }}">
            @error('address') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
          </label>
          <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </form>
      </section>

      <section class="portal-panel">
        <div class="section-heading">
          <h2>Password</h2>
          <span>Proteggi il tuo account</span>
        </div>
        <form method="POST" action="/patient/password" class="profile-form">
          @csrf
          @method('PUT')
          <div class="profile-grid">
            <label class="form-label">
              Password attuale
              <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" autocomplete="current-password" required>
              @error('current_password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label">
              Nuova password
              <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" autocomplete="new-password" required>
              @error('password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label">
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
