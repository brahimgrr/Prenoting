@extends('layouts.guest', ['title' => 'Crea account - MedPortal'])

@section('content')
  <main class="auth-page">
    <section class="auth-panel auth-panel--wide">
      <a class="auth-panel__back-link" href="{{ route('home') }}">Torna alla pagina iniziale</a>

      <div class="auth-panel__header">
        <span class="app-brand__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">M</span>
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
            <label class="form-label" for="email">Email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
            @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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

  <script>
    (() => {
      const COMUNE_SUGGESTION_LIMIT = 8;
      let suppressComuneFocusUpdate = false;

      function normalizzaComune(value) {
        return value.trim().toLocaleUpperCase("it-IT");
      }

      function nascondiSuggerimentiComune(combobox) {
        const suggestions = combobox.querySelector("[data-comune-suggestions]");

        suggestions?.setAttribute("hidden", "");
        suggestions?.querySelectorAll("[data-comune-option]").forEach((option) => {
          option.hidden = true;
        });
      }

      function aggiornaSuggerimentiComune(input) {
        const combobox = input.closest("[data-comune-combobox]");
        const suggestions = combobox?.querySelector("[data-comune-suggestions]");
        if (!combobox || !suggestions) return;

        const query = normalizzaComune(input.value);
        let shown = 0;

        suggestions.querySelectorAll("[data-comune-option]").forEach((option) => {
          const matches =
            query.length > 0 &&
            normalizzaComune(option.value).includes(query) &&
            shown < COMUNE_SUGGESTION_LIMIT;

          option.hidden = !matches;
          if (matches) shown += 1;
        });

        suggestions.toggleAttribute("hidden", shown === 0);
      }

      function opzioniComuneVisibili(combobox) {
        return Array.from(combobox.querySelectorAll("[data-comune-option]")).filter(
          (option) => !option.hidden
        );
      }

      function selezionaOpzioneComune(option) {
        const combobox = option.closest("[data-comune-combobox]");
        const input = combobox?.querySelector("[data-comune-input]");
        if (!combobox || !input) return;

        suppressComuneFocusUpdate = true;
        input.value = option.value;
        input.focus();
        nascondiSuggerimentiComune(combobox);

        requestAnimationFrame(() => {
          suppressComuneFocusUpdate = false;
        });
      }

      document.addEventListener("input", (event) => {
        const comuneInput = event.target.closest("[data-comune-input]");
        if (!comuneInput) return;

        aggiornaSuggerimentiComune(comuneInput);
      });

      document.addEventListener("focusin", (event) => {
        const comuneInput = event.target.closest("[data-comune-input]");
        if (!comuneInput || suppressComuneFocusUpdate) return;

        aggiornaSuggerimentiComune(comuneInput);
      });

      document.addEventListener("keydown", (event) => {
        const comuneInput = event.target.closest("[data-comune-input]");
        if (comuneInput) {
          const combobox = comuneInput.closest("[data-comune-combobox]");
          const options = combobox ? opzioniComuneVisibili(combobox) : [];

          if (event.key === "ArrowDown" && options.length > 0) {
            event.preventDefault();
            options[0].focus();
          }

          if (event.key === "Escape" && combobox) {
            nascondiSuggerimentiComune(combobox);
          }

          return;
        }

        const comuneOption = event.target.closest("[data-comune-option]");
        if (!comuneOption) return;

        const combobox = comuneOption.closest("[data-comune-combobox]");
        const options = combobox ? opzioniComuneVisibili(combobox) : [];
        const currentIndex = options.indexOf(comuneOption);

        if (event.key === "ArrowDown" && options[currentIndex + 1]) {
          event.preventDefault();
          options[currentIndex + 1].focus();
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          if (options[currentIndex - 1]) {
            options[currentIndex - 1].focus();
            return;
          }

          combobox?.querySelector("[data-comune-input]")?.focus();
        }

        if (event.key === "Escape" && combobox) {
          nascondiSuggerimentiComune(combobox);
          combobox.querySelector("[data-comune-input]")?.focus();
        }
      });

      document.addEventListener("click", (event) => {
        const comuneOption = event.target.closest("[data-comune-option]");
        if (comuneOption) {
          selezionaOpzioneComune(comuneOption);
          return;
        }

        document.querySelectorAll("[data-comune-combobox]").forEach((combobox) => {
          if (!combobox.contains(event.target)) {
            nascondiSuggerimentiComune(combobox);
          }
        });
      });
    })();
  </script>
@endsection
