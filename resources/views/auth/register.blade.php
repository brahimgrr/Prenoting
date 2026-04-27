@extends('layouts.guest', ['title' => 'Crea account - MedPortal'])

@section('content')
  <main class="auth-page">
    <section class="auth-panel auth-panel--wide">
      <div class="auth-panel__header">
        <span class="app-brand__mark">M</span>
        <div>
          <h1>Crea account</h1>
          <p>Registrati come paziente per prenotare e gestire gli appuntamenti.</p>
        </div>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="/register" novalidate>
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="first_name">Nome</label>
            <input class="form-control" id="first_name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="last_name">Cognome</label>
            <input class="form-control" id="last_name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}">
          </div>
          <div class="col-12">
            <label class="form-label" for="username">Email</label>
            <input class="form-control @error('username') is-invalid @enderror" id="username" name="username" type="email" autocomplete="email" value="{{ old('username') }}" required>
            @error('username') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Telefono</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" required>
            @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
        </div>
        <button class="btn btn-primary w-100 mt-4" type="submit">Crea account</button>
      </form>

      <p class="auth-panel__footer">
        Hai gia un account? <a href="/login">Accedi</a>
      </p>
    </section>
  </main>
@endsection
