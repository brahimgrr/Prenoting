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
          <h2>Orari ambulatorio</h2>
        </div>
        <form method="POST" action="/doctor/profile/working-hours" class="profile-form working-hours-form">
          @csrf
          @method('PATCH')
          @error('working_hours') <div class="alert alert-danger">{{ $message }}</div> @enderror

          <div class="working-hours-list">
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
              @endphp
              <section class="working-hours-day" data-working-hours-day data-weekday="{{ $weekday }}" data-next-index="{{ count($dayRows) }}">
                <div class="working-hours-day__header">
                  <strong>{{ $label }}</strong>
                </div>
                <div class="working-hours-day__rows" data-working-hours-rows>
                  @foreach ($dayRows as $index => $row)
                    <div class="working-hours-row" data-working-hours-row>
                      <label class="form-label">
                        Inizio
                        <x-time-select
                          class="form-control"
                          name="working_hours[{{ $weekday }}][{{ $index }}][start_time]"
                          :value="$row['start_time'] ?? ''"
                        />
                      </label>
                      <label class="form-label">
                        Fine
                        <x-time-select
                          class="form-control"
                          name="working_hours[{{ $weekday }}][{{ $index }}][end_time]"
                          :value="$row['end_time'] ?? ''"
                        />
                      </label>
                      <button class="btn btn-outline-secondary working-hours-row__remove" type="button" data-working-hours-remove aria-label="Rimuovi fascia">Rimuovi</button>
                    </div>
                  @endforeach
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-working-hours-add>Aggiungi fascia</button>
              </section>
            @endforeach
          </div>

          <button type="submit" class="btn btn-primary">Salva orari</button>
        </form>
      </section>
    </div>
  </section>
@endsection
