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
  $emptyWorkingHourRow = ['start_time' => '', 'end_time' => ''];
  $defaultWorkingHourRow = ['start_time' => '09:00', 'end_time' => '12:00'];
  $hasConfiguredWorkingHours = collect($workingHourDays ?? [])
    ->flatMap(fn ($day) => $day['rows'] ?? [])
    ->contains(fn ($row) => filled($row['start_time'] ?? null) || filled($row['end_time'] ?? null));
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

      <section class="portal-panel">
        <div class="section-heading">
          <h2>Password</h2>
        </div>
        <form method="POST" action="/doctor/password" class="profile-form">
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

      <section class="portal-panel">
        <form method="POST" action="/doctor/profile/working-hours" class="m-0">
          @csrf
          @method('PATCH')
          @error('working_hours') <div class="alert alert-danger">{{ $message }}</div> @enderror

          <div class="section-heading">
            <h2>Orari ambulatorio</h2>
          </div>

          <div class="border-bottom pb-3 mb-3" data-working-hours-render-summary>
            <div class="form-label mb-2">Orari di apertura</div>
            <div class="row g-2">
              @foreach ($weekdays as $weekday => $label)
                @php
                  if (is_array($oldWorkingHours)) {
                    $summaryRows = array_values($oldWorkingHours[$weekday] ?? $oldWorkingHours[(string) $weekday] ?? [$emptyWorkingHourRow]);
                  } elseif (! $hasConfiguredWorkingHours) {
                    $summaryRows = [$weekday <= 5 ? $defaultWorkingHourRow : $emptyWorkingHourRow];
                  } else {
                    $summaryRows = $workingHourDays[$weekday]['rows'] ?? [$emptyWorkingHourRow];
                  }

                  $summarySlots = collect($summaryRows)
                    ->filter(fn ($row) => filled($row['start_time'] ?? null) && filled($row['end_time'] ?? null))
                    ->map(fn ($row) => ($row['start_time'] ?? '').' - '.($row['end_time'] ?? ''))
                    ->values();
                @endphp
                <div class="col-12 col-md-6">
                  <div class="d-flex justify-content-between gap-3">
                    <span class="text-secondary">{{ $label }}</span>
                    <span>{{ $summarySlots->isNotEmpty() ? $summarySlots->join(', ') : 'Chiuso' }}</span>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          <div class="vstack gap-3">
            <div class="form-label mb-0">Orari di apertura</div>

            @foreach ($weekdays as $weekday => $label)
              @php
                if (is_array($oldWorkingHours)) {
                  $dayRows = array_values($oldWorkingHours[$weekday] ?? $oldWorkingHours[(string) $weekday] ?? [$emptyWorkingHourRow]);
                } elseif (! $hasConfiguredWorkingHours) {
                  $dayRows = [$weekday <= 5 ? $defaultWorkingHourRow : $emptyWorkingHourRow];
                } else {
                  $dayRows = $workingHourDays[$weekday]['rows'] ?? [$emptyWorkingHourRow];
                  $dayRows = collect($dayRows)
                    ->filter(fn ($row) => filled($row['start_time'] ?? null) || filled($row['end_time'] ?? null))
                    ->values()
                    ->all();
                }

                if (count($dayRows) === 0) {
                  $dayRows = [$hasConfiguredWorkingHours || $weekday > 5 ? $emptyWorkingHourRow : $defaultWorkingHourRow];
                }

                $dayMinutes = collect($dayRows)->sum(function ($row) {
                  $start = $row['start_time'] ?? null;
                  $end = $row['end_time'] ?? null;

                  if (! filled($start) || ! filled($end)) {
                    return 0;
                  }

                  [$startHour, $startMinute] = array_map('intval', explode(':', $start));
                  [$endHour, $endMinute] = array_map('intval', explode(':', $end));

                  return max(0, (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute));
                });
                $isOpen = collect($dayRows)->contains(fn ($row) => filled($row['start_time'] ?? null) || filled($row['end_time'] ?? null));
                $dayHours = intdiv($dayMinutes, 60);
                $dayRemainder = $dayMinutes % 60;
                $dayDuration = $dayMinutes > 0
                  ? ($dayHours.($dayRemainder > 0 ? 'h '.$dayRemainder.'m' : 'h'))
                  : null;
              @endphp
              <section class="border-top pt-3{{ $isOpen ? '' : ' text-secondary' }}" data-working-hours-day data-weekday="{{ $weekday }}" data-next-index="{{ count($dayRows) }}">
                <div class="d-flex justify-content-between gap-3 mb-2">
                  <span>{{ $label }}</span>
                  <span class="{{ $isOpen ? 'text-primary' : 'text-secondary' }}" data-working-hours-status>
                    @if ($isOpen)
                      Aperto @if ($dayDuration)- {{ $dayDuration }}@endif
                    @else
                      Chiuso
                    @endif
                  </span>
                </div>

                <div data-working-hours-controls>
                  <div class="vstack gap-2" data-working-hours-rows>
                    @foreach ($dayRows as $index => $row)
                      @php
                        $startId = "working-hours-{$weekday}-{$index}-start";
                        $endId = "working-hours-{$weekday}-{$index}-end";
                      @endphp
                      <div class="row g-2 align-items-end" data-working-hours-row>
                        <div class="col-12 col-md">
                          <label class="form-label" for="{{ $startId }}">Apri alle</label>
                          <x-time-select
                            id="{{ $startId }}"
                            class="form-select"
                            name="working_hours[{{ $weekday }}][{{ $index }}][start_time]"
                            :value="$row['start_time'] ?? ''"
                            :disabled="! $isOpen"
                          />
                        </div>

                        <div class="col-12 col-md">
                          <label class="form-label" for="{{ $endId }}">Chiudi alle</label>
                          <x-time-select
                            id="{{ $endId }}"
                            class="form-select"
                            name="working_hours[{{ $weekday }}][{{ $index }}][end_time]"
                            :value="$row['end_time'] ?? ''"
                            :include-end-of-day="true"
                            :disabled="! $isOpen"
                          />
                        </div>

                        <div class="col-12 col-md-auto">
                          <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary" type="button" data-working-hours-add aria-label="Aggiungi un'altra fascia oraria">+</button>
                            <button class="btn btn-outline-secondary" type="button" data-working-hours-remove aria-label="Rimuovi fascia">-</button>
                          </div>
                        </div>
                      </div>
                    @endforeach
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
@endsection
