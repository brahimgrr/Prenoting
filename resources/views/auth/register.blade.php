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
            <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" required>
            @error('first_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="last_name">Cognome</label>
            <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" required>
            @error('last_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
            <label class="form-label" for="password_confirmation">Conferma password</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="date_of_birth">Data di nascita</label>
            <input class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" required>
            @error('date_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="gender">Sesso</label>
            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
              <option value="" @selected(old('gender') === null)>Seleziona</option>
              <option value="M" @selected(old('gender') === 'M')>Maschile</option>
              <option value="F" @selected(old('gender') === 'F')>Femminile</option>
            </select>
            @error('gender') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Telefono</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" required>
            @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="place_of_birth">Luogo di nascita</label>
            <div class="comune-combobox" data-comune-combobox>
              <input
                class="form-control @error('place_of_birth') is-invalid @enderror"
                id="place_of_birth"
                name="place_of_birth"
                autocomplete="off"
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="comuni-suggestions"
                data-comune-input
                value="{{ old('place_of_birth') }}"
                required
              >
              <div class="comune-suggestions" id="comuni-suggestions" role="listbox" data-comune-suggestions hidden>
                @foreach($comuni as $comune)
                  <button type="button" class="comune-suggestion" role="option" data-comune-option value="{{ $comune }}" hidden>{{ $comune }}</button>
                @endforeach
              </div>
            </div>
            @error('place_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
