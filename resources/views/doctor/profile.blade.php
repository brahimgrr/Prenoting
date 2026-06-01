@extends('layouts.portal', ['title' => 'Profilo medico - MedPortal'])

@php
  $user = auth()->user();
  $profile = $doctorProfile ?? $user->doctorProfile;
  $weekdays = [
    1 => 'Lunedi',
    2 => 'Martedi',
    3 => 'Mercoledi',
    4 => 'Giovedi',
    5 => 'Venerdi',
    6 => 'Sabato',
    7 => 'Domenica',
  ];
  $oldWorkingHours = old('working_hours');
  $emptyWorkingHourDay = [
    'open_time' => '',
    'close_time' => '',
    'break_start_time' => '',
    'break_end_time' => '',
  ];
  $defaultWorkingHourDay = [
    'open_time' => '09:00',
    'close_time' => '12:00',
    'break_start_time' => '',
    'break_end_time' => '',
  ];
  $hasConfiguredWorkingHours = collect($workingHourDays ?? [])
    ->contains(fn ($day) => collect($day['fields'] ?? [])->contains(fn ($value) => filled($value)));
@endphp

@section('content')
  <section class="portal-section profile-page container-xl">
    <div class="portal-page-heading mb-4">
      <span class="portal-eyebrow">Profilo</span>
      <h1>Profilo medico</h1>
    </div>

    <div class="profile-stack d-grid gap-3">
      <section class="portal-panel profile-readonly-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Dati professionali</h2>
        </div>
        <div class="profile-data-grid row g-3">
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Nome visualizzato</span>
              <strong class="d-block mt-1">{{ $profile?->display_name ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Numero di iscrizione all'albo</span>
              <strong class="d-block mt-1">{{ $profile?->license_number ?: '-' }}</strong>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="profile-data-tile h-100 p-3">
              <span class="d-block">Email</span>
              <strong class="d-block mt-1">{{ $user->email ?: '-' }}</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="portal-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Contatti ambulatorio</h2>
        </div>
        <form method="POST" action="/doctor/profile" class="profile-form d-grid gap-3">
          @csrf
          @method('PATCH')
          <div class="profile-field-list d-grid gap-3">
            <label class="form-label d-grid gap-2 mb-0">
              Telefono
              <input class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $profile?->phone) }}">
              @error('phone') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
          </div>
          <label class="form-label d-grid gap-2 mb-0">
            Luogo ambulatorio
            <input class="form-control @error('clinic_address') is-invalid @enderror" name="clinic_address" value="{{ old('clinic_address', $profile?->clinic_address) }}" required>
            @error('clinic_address') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
          </label>
          <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </form>
      </section>

      <section class="portal-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Password</h2>
        </div>
        <form method="POST" action="/doctor/password" class="profile-form d-grid gap-3">
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

      <section class="portal-panel p-4">
        <form method="POST" action="/doctor/profile/working-hours" class="m-0">
          @csrf
          @method('PATCH')
          @error('working_hours') <div class="alert alert-danger">{{ $message }}</div> @enderror

          <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2>Orari ambulatorio</h2>
          </div>

          <div class="mb-3">
            <div class="row g-2">
              @foreach ($weekdays as $weekday => $label)
                @php
                  if (is_array($oldWorkingHours)) {
                    $summaryDay = array_replace($emptyWorkingHourDay, $oldWorkingHours[$weekday] ?? $oldWorkingHours[(string) $weekday] ?? []);
                  } elseif (! $hasConfiguredWorkingHours) {
                    $summaryDay = $weekday <= 5 ? $defaultWorkingHourDay : $emptyWorkingHourDay;
                  } else {
                    $summaryDay = array_replace($emptyWorkingHourDay, $workingHourDays[$weekday]['fields'] ?? []);
                  }

                  $summarySlots = collect();
                  if (filled($summaryDay['open_time'] ?? null) && filled($summaryDay['close_time'] ?? null)) {
                    if (filled($summaryDay['break_start_time'] ?? null) && filled($summaryDay['break_end_time'] ?? null)) {
                      $summarySlots->push(($summaryDay['open_time'] ?? '').' - '.($summaryDay['break_start_time'] ?? ''));
                      $summarySlots->push(($summaryDay['break_end_time'] ?? '').' - '.($summaryDay['close_time'] ?? ''));
                    } else {
                      $summarySlots->push(($summaryDay['open_time'] ?? '').' - '.($summaryDay['close_time'] ?? ''));
                    }
                  }
                @endphp
                <div class="col-12 col-md-6">
                  <div class="d-flex justify-content-between gap-3">
                    <strong class="text-secondary">{{ $label }}</strong>
                    <span>{{ $summarySlots->isNotEmpty() ? $summarySlots->join(', ') : 'Chiuso' }}</span>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          <div class="vstack gap-3">

            @foreach ($weekdays as $weekday => $label)
              @php
                if (is_array($oldWorkingHours)) {
                  $day = array_replace($emptyWorkingHourDay, $oldWorkingHours[$weekday] ?? $oldWorkingHours[(string) $weekday] ?? []);
                } elseif (! $hasConfiguredWorkingHours) {
                  $day = $weekday <= 5 ? $defaultWorkingHourDay : $emptyWorkingHourDay;
                } else {
                  $day = array_replace($emptyWorkingHourDay, $workingHourDays[$weekday]['fields'] ?? []);
                }

                $toMinutes = function (?string $time): ?int {
                  if (! filled($time) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
                    return null;
                  }

                  [$hour, $minute] = array_map('intval', explode(':', $time));

                  return ($hour * 60) + $minute;
                };
                $openMinutes = $toMinutes($day['open_time'] ?? null);
                $closeMinutes = $toMinutes($day['close_time'] ?? null);
                $breakStartMinutes = $toMinutes($day['break_start_time'] ?? null);
                $breakEndMinutes = $toMinutes($day['break_end_time'] ?? null);
                $dayMinutes = $openMinutes !== null && $closeMinutes !== null && $closeMinutes > $openMinutes
                  ? $closeMinutes - $openMinutes
                  : 0;
                if ($breakStartMinutes !== null && $breakEndMinutes !== null && $breakEndMinutes > $breakStartMinutes) {
                  $dayMinutes -= $breakEndMinutes - $breakStartMinutes;
                }
                $dayMinutes = max(0, $dayMinutes);
                $isOpen = filled($day['open_time'] ?? null) || filled($day['close_time'] ?? null);
                $dayHours = intdiv($dayMinutes, 60);
                $dayRemainder = $dayMinutes % 60;
                $dayDuration = $dayMinutes > 0
                  ? ($dayHours.($dayRemainder > 0 ? 'h '.$dayRemainder.'m' : 'h'))
                  : null;
              @endphp
              <section class="border-top pt-3{{ $isOpen ? '' : ' text-secondary' }}" data-working-hours-day data-weekday="{{ $weekday }}">
                <div class="d-flex justify-content-between gap-3 mb-2">
                  <strong>{{ $label }}</strong>
                  <span class="{{ $isOpen ? 'text-primary' : 'text-secondary' }}" data-working-hours-status>
                    @if ($isOpen)
                      Aperto @if ($dayDuration)- {{ $dayDuration }}@endif
                    @else
                      Chiuso
                    @endif
                  </span>
                </div>

                <div class="vstack gap-2">
                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="working-hours-{{ $weekday }}-open">Apri alle</label>
                      <x-time-select
                        id="working-hours-{{ $weekday }}-open"
                        class="form-select"
                        name="working_hours[{{ $weekday }}][open_time]"
                        :value="$day['open_time'] ?? ''"
                      />
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label" for="working-hours-{{ $weekday }}-close">Chiudi alle</label>
                      <x-time-select
                        id="working-hours-{{ $weekday }}-close"
                        class="form-select"
                        name="working_hours[{{ $weekday }}][close_time]"
                        :value="$day['close_time'] ?? ''"
                        :include-end-of-day="true"
                      />
                    </div>
                  </div>

                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="working-hours-{{ $weekday }}-break-start">Pausa pranzo da</label>
                      <x-time-select
                        id="working-hours-{{ $weekday }}-break-start"
                        class="form-select"
                        name="working_hours[{{ $weekday }}][break_start_time]"
                        :value="$day['break_start_time'] ?? ''"
                      />
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label" for="working-hours-{{ $weekday }}-break-end">Pausa pranzo a</label>
                      <x-time-select
                        id="working-hours-{{ $weekday }}-break-end"
                        class="form-select"
                        name="working_hours[{{ $weekday }}][break_end_time]"
                        :value="$day['break_end_time'] ?? ''"
                        :include-end-of-day="true"
                      />
                    </div>
                  </div>
                </div>
              </section>
            @endforeach
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 mt-3 border-top">
            <a class="btn btn-outline-secondary" href="/doctor/profile">Reset</a>
            <button type="submit" class="btn btn-primary">Salva orari</button>
          </div>

        </form>
      </section>
    </div>
  </section>

  <script>
    (() => {
      function orarioInMinuti(value) {
        const match = /^(\d{2}):(\d{2})$/.exec(value);
        if (!match) return null;

        return Number(match[1]) * 60 + Number(match[2]);
      }

      function minutiGiornoOrario(day) {
        const openMinutes = orarioInMinuti(day.querySelector('select[name$="[open_time]"]')?.value || "");
        const closeMinutes = orarioInMinuti(day.querySelector('select[name$="[close_time]"]')?.value || "");
        const breakStartMinutes = orarioInMinuti(day.querySelector('select[name$="[break_start_time]"]')?.value || "");
        const breakEndMinutes = orarioInMinuti(day.querySelector('select[name$="[break_end_time]"]')?.value || "");

        if (openMinutes === null || closeMinutes === null || closeMinutes <= openMinutes) return 0;

        const breakMinutes = breakStartMinutes !== null && breakEndMinutes !== null && breakEndMinutes > breakStartMinutes
          ? breakEndMinutes - breakStartMinutes
          : 0;

        return Math.max(0, closeMinutes - openMinutes - breakMinutes);
      }

      function etichettaDurataOrario(minutes) {
        if (minutes <= 0) return "";

        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        return `${hours}h${remainder > 0 ? ` ${remainder}m` : ""}`;
      }

      function aggiornaGiornoOrario(day) {
        const status = day.querySelector("[data-working-hours-status]");
        const hasValues = Array.from(day.querySelectorAll("select")).some((select) => select.value);

        day.classList.toggle("text-secondary", !hasValues);

        if (status) {
          status.classList.toggle("text-primary", hasValues);
          status.classList.toggle("text-secondary", !hasValues);
          const duration = etichettaDurataOrario(minutiGiornoOrario(day));
          status.textContent = hasValues ? `Aperto${duration ? ` - ${duration}` : ""}` : "Chiuso";
        }
      }

      document.addEventListener("change", (event) => {
        const day = event.target.closest?.("[data-working-hours-day]");
        if (day && event.target.matches?.("select")) aggiornaGiornoOrario(day);
      });
    })();
  </script>
@endsection
