@extends('layouts.guest', ['title' => 'Accedi - MedPortal'])

@section('content')
  <main class="auth-page">
    <section class="auth-panel">
      <div class="auth-panel__header">
        <span class="app-brand__mark">M</span>
        <div>
          <h1>Accedi</h1>
          <p>Accedi al portale delle prenotazioni mediche.</p>
        </div>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="/login" novalidate>
        @csrf
        <div class="mb-3">
          <label class="form-label" for="username">Username o email</label>
          <input class="form-control @error('username') is-invalid @enderror" id="username" name="username" autocomplete="username" value="{{ old('username') }}" required>
          @error('username') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required>
          @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <button class="btn btn-primary w-100" type="submit">Accedi</button>
      </form>

      <p class="auth-panel__footer">
        Nuovo paziente? <a href="/register">Crea un account</a>
      </p>
    </section>
  </main>
@endsection
