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
        <form method="POST" action="/doctor/profile/working-hours" class="m-0">
          @csrf
          @method('PATCH')
          @error('working_hours') <div class="alert alert-danger">{{ $message }}</div> @enderror

          <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
            <div>
              <h2 class="h4 fw-bold mb-0">Orari ambulatorio</h2>
              <small class="text-secondary">Imposta le fasce orarie settimanali</small>
            </div>
          </div>

          <div class="card border-0 shadow-sm">
            <div class="list-group list-group-flush">
              @php
                $weeklyOpenDays = 0;
                $weeklyMinutes = 0;
              @endphp
              @foreach ($weekdays as $weekday => $label)
              @php
                if (is_array($oldWorkingHours)) {
                  $dayRows = array_values($oldWorkingHours[$weekday] ?? $oldWorkingHours[(string) $weekday] ?? [$emptyWorkingHourRow]);
                } elseif (! $hasConfiguredWorkingHours) {
                  $dayRows = [$weekday <= 5 ? $defaultWorkingHourRow : $emptyWorkingHourRow];
                } else {
                  $dayRows = $workingHourDays[$weekday]['rows'] ?? [$emptyWorkingHourRow];
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
                $weeklyOpenDays += $isOpen ? 1 : 0;
                $weeklyMinutes += $dayMinutes;
                $switchId = "working-hours-switch-{$weekday}";
              @endphp
              <section class="list-group-item p-3 p-md-4 {{ $isOpen ? '' : 'bg-body-tertiary' }}" data-working-hours-day data-weekday="{{ $weekday }}" data-next-index="{{ count($dayRows) }}">
                <div class="row g-3 {{ $isOpen ? 'align-items-start' : 'align-items-center' }}">
                  <div class="col-md-3">
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" role="switch" id="{{ $switchId }}" data-working-hours-toggle @checked($isOpen)>
                      <label class="form-check-label fw-semibold text-uppercase small {{ $isOpen ? '' : 'text-secondary' }}" for="{{ $switchId }}">{{ $label }}</label>
                    </div>
                    <div class="{{ $isOpen ? 'text-primary' : 'text-secondary' }} small mt-1" data-working-hours-status>
                      @if ($isOpen)
                        Aperto @if ($dayDuration)&middot; {{ $dayDuration }}@endif
                      @else
                        Chiuso
                      @endif
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div class="{{ $isOpen ? 'd-flex' : 'd-none' }} flex-wrap gap-2" data-working-hours-controls>
                      <div class="d-flex flex-wrap gap-2" data-working-hours-rows>
                        @foreach ($dayRows as $index => $row)
                          <div class="input-group input-group-sm w-auto" data-working-hours-row>
                            <x-time-select
                              class="form-select form-select-sm text-center"
                              name="working_hours[{{ $weekday }}][{{ $index }}][start_time]"
                              :value="$row['start_time'] ?? ''"
                            />
                            <span class="input-group-text bg-transparent px-2">a</span>
                            <x-time-select
                              class="form-select form-select-sm text-center"
                              name="working_hours[{{ $weekday }}][{{ $index }}][end_time]"
                              :value="$row['end_time'] ?? ''"
                            />
                            <button class="btn btn-outline-danger border-start-0" type="button" data-working-hours-remove aria-label="Rimuovi fascia">x</button>
                          </div>
                        @endforeach
                      </div>
                      <button class="btn btn-sm btn-outline-primary" type="button" data-working-hours-add>Aggiungi fascia</button>
                    </div>
                    <div class="{{ $isOpen ? 'd-none' : '' }} text-secondary small fst-italic" data-working-hours-empty>
                      Attiva l'interruttore per impostare gli orari
                    </div>
                  </div>
                </div>
              </section>
              @endforeach
            </div>

            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
              <a class="btn btn-outline-secondary" href="/doctor/profile">Reset</a>
              <button type="submit" class="btn btn-primary">Salva orari</button>
            </div>
          </div>

        </form>
      </section>
    </div>
  </section>
@endsection
