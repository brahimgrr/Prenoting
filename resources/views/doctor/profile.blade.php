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
        </div>
      </section>

      <section class="portal-panel p-4">
        <div class="section-heading d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2>Contatti ambulatorio</h2>
        </div>
        <form method="POST" action="/doctor/profile" class="profile-form d-grid gap-3">
          @csrf
          @method('PATCH')
          <div class="profile-grid row g-3">
            <label class="form-label col-12 col-md-6 d-grid gap-2 mb-0">
              Email
              <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email">
              @error('email') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label col-12 col-md-6 d-grid gap-2 mb-0">
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
          <div class="profile-grid row g-3">
            <label class="form-label col-12 col-md-4 d-grid gap-2 mb-0">
              Password attuale
              <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" autocomplete="current-password" required>
              @error('current_password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label col-12 col-md-4 d-grid gap-2 mb-0">
              Nuova password
              <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" autocomplete="new-password" required>
              @error('password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </label>
            <label class="form-label col-12 col-md-4 d-grid gap-2 mb-0">
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

  <script>
    (() => {
      const DEFAULT_WORKING_START = "09:00";
      const DEFAULT_WORKING_END = "12:00";
      const WORKING_HOUR_AUTOFILL_MINUTES = 60;

      function opzioniOrarieMezzOra(selectedValue = "", includeEndOfDay = false) {
        let options = selectedValue
          ? `<option value="${selectedValue}" selected>${selectedValue}</option><option value="">--:--</option>`
          : '<option value="">--:--</option>';

        for (let hour = 0; hour < 24; hour += 1) {
          for (const minute of [0, 30]) {
            const value = `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
            if (value !== selectedValue) {
              options += `<option value="${value}">${value}</option>`;
            }
          }
        }

        if (includeEndOfDay && selectedValue !== "24:00") {
          options += '<option value="24:00">24:00</option>';
        }

        return options;
      }

      function templateSelettoreOrario(name, selectedValue = "", includeEndOfDay = false) {
        return `<select class="form-select" name="${name}">${opzioniOrarieMezzOra(selectedValue, includeEndOfDay)}</select>`;
      }

      function orarioInMinuti(value) {
        const match = /^(\d{2}):(\d{2})$/.exec(value);
        if (!match) return null;

        return Number(match[1]) * 60 + Number(match[2]);
      }

      function minutiInOrario(minutes, includeEndOfDay = false) {
        const lastOptionMinutes = includeEndOfDay ? 24 * 60 : (23 * 60) + 30;
        if (minutes < 0 || minutes > lastOptionMinutes) return "";

        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
      }

      function aggiungiMinutiAOrario(value, minutesToAdd, includeEndOfDay = false) {
        const minutes = orarioInMinuti(value);
        if (minutes === null) return "";

        return minutiInOrario(minutes + minutesToAdd, includeEndOfDay);
      }

      function suggerisciFineOrario(startValue) {
        return aggiungiMinutiAOrario(startValue, WORKING_HOUR_AUTOFILL_MINUTES, true);
      }

      function fineOrarioPrecedente(rows) {
        return Array.from(rows.querySelectorAll('[data-working-hours-row] select[name$="[end_time]"]'))
          .reverse()
          .find((select) => select.value)?.value || "";
      }

      function valoriPredefinitiNuovaFasciaOraria(rows) {
        const start = aggiungiMinutiAOrario(fineOrarioPrecedente(rows), WORKING_HOUR_AUTOFILL_MINUTES) || DEFAULT_WORKING_START;
        const end = suggerisciFineOrario(start) || DEFAULT_WORKING_END;

        return { start, end };
      }

      function templateFasciaOraria(weekday, index, startValue = "", endValue = "") {
        return `
          <div class="row g-2 align-items-end" data-working-hours-row>
            <div class="col-12 col-md">
              <label class="form-label">Apri alle</label>
              ${templateSelettoreOrario(`working_hours[${weekday}][${index}][start_time]`, startValue)}
            </div>
            <div class="col-12 col-md">
              <label class="form-label">Chiudi alle</label>
              ${templateSelettoreOrario(`working_hours[${weekday}][${index}][end_time]`, endValue, true)}
            </div>
            <div class="col-12 col-md-auto">
              <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" data-working-hours-add aria-label="Aggiungi un'altra fascia oraria">+</button>
                <button class="btn btn-outline-secondary" type="button" data-working-hours-remove aria-label="Rimuovi fascia">-</button>
              </div>
            </div>
          </div>
        `;
      }

      function fasceOrarieConValori(day) {
        return Array.from(day.querySelectorAll("[data-working-hours-row] select")).some((select) => select.value);
      }

      function minutiGiornoOrario(day) {
        return Array.from(day.querySelectorAll("[data-working-hours-row]")).reduce((total, row) => {
          const start = row.querySelector('select[name$="[start_time]"]')?.value;
          const end = row.querySelector('select[name$="[end_time]"]')?.value;
          const startMinutes = orarioInMinuti(start || "");
          const endMinutes = orarioInMinuti(end || "");

          if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) return total;

          return total + (endMinutes - startMinutes);
        }, 0);
      }

      function etichettaDurataOrario(minutes) {
        if (minutes <= 0) return "";

        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        return `${hours}h${remainder > 0 ? ` ${remainder}m` : ""}`;
      }

      function impostaGiornoOrarioAperto(day, isOpen, fillDefaults = false) {
        const rows = day.querySelector("[data-working-hours-rows]");
        const emptyMessage = day.querySelector("[data-working-hours-empty]");
        const status = day.querySelector("[data-working-hours-status]");

        if (!rows) return;

        if (!isOpen) {
          const firstRow = rows.querySelector("[data-working-hours-row]");
          rows.querySelectorAll("[data-working-hours-row]").forEach((row, index) => {
            if (index > 0) row.remove();
          });
          firstRow?.querySelectorAll("select").forEach((select) => {
            select.value = "";
          });
          day.setAttribute("data-next-index", "1");
        } else if (fillDefaults && !fasceOrarieConValori(day)) {
          const firstRow = rows.querySelector("[data-working-hours-row]");
          const startSelect = firstRow?.querySelector('select[name$="[start_time]"]');
          const endSelect = firstRow?.querySelector('select[name$="[end_time]"]');
          if (startSelect) startSelect.value = DEFAULT_WORKING_START;
          if (endSelect) endSelect.value = DEFAULT_WORKING_END;
        }

        emptyMessage?.classList.toggle("d-none", isOpen);
        day.classList.toggle("text-secondary", !isOpen);
        day.querySelectorAll("[data-working-hours-row] select").forEach((control) => {
          control.disabled = !isOpen;
        });
        day.querySelectorAll("[data-working-hours-add], [data-working-hours-remove]").forEach((button) => {
          button.disabled = false;
        });

        if (status) {
          status.classList.toggle("text-primary", isOpen);
          status.classList.toggle("text-secondary", !isOpen);
          const duration = etichettaDurataOrario(minutiGiornoOrario(day));
          status.textContent = isOpen ? `Aperto${duration ? ` - ${duration}` : ""}` : "Chiuso";
        }
      }

      document.addEventListener("change", (event) => {
        const workingHoursSelect = event.target.closest?.("[data-working-hours-row] select");
        const startSelect = event.target.matches?.('[data-working-hours-row] select[name$="[start_time]"]')
          ? event.target
          : null;
        if (!workingHoursSelect) return;

        const row = workingHoursSelect.closest("[data-working-hours-row]");
        if (startSelect) {
          const endSelect = row?.querySelector('select[name$="[end_time]"]');
          if (endSelect) {
            endSelect.value = suggerisciFineOrario(startSelect.value);
          }
        }

        const day = row?.closest("[data-working-hours-day]");
        if (day) impostaGiornoOrarioAperto(day, fasceOrarieConValori(day));
      });

      document.addEventListener("click", (event) => {
        const addWorkingHoursRow = event.target.closest("[data-working-hours-add]");
        if (addWorkingHoursRow) {
          const day = addWorkingHoursRow.closest("[data-working-hours-day]");
          const rows = day?.querySelector("[data-working-hours-rows]");
          if (!day || !rows) return;

          const weekday = day.getAttribute("data-weekday");

          if (!fasceOrarieConValori(day)) {
            impostaGiornoOrarioAperto(day, true, true);
            rows.querySelector("[data-working-hours-row] select")?.focus();
            return;
          }

          const index = Number(day.getAttribute("data-next-index") || "0");
          const defaults = valoriPredefinitiNuovaFasciaOraria(rows);
          rows.insertAdjacentHTML("beforeend", templateFasciaOraria(weekday, index, defaults.start, defaults.end));
          day.setAttribute("data-next-index", String(index + 1));
          impostaGiornoOrarioAperto(day, true);
          rows.querySelector("[data-working-hours-row]:last-child select")?.focus();
          return;
        }

        const removeWorkingHoursRow = event.target.closest("[data-working-hours-remove]");
        if (removeWorkingHoursRow) {
          const row = removeWorkingHoursRow.closest("[data-working-hours-row]");
          const day = row?.closest("[data-working-hours-day]");
          const rows = row?.parentElement;
          if (!row || !rows) return;

          if (rows.querySelectorAll("[data-working-hours-row]").length <= 1) {
            if (day) {
              impostaGiornoOrarioAperto(day, false);
            } else {
              row.querySelectorAll("select").forEach((select) => {
                select.value = "";
              });
              row.querySelector("select")?.focus();
            }
            return;
          }

          row.remove();
          if (day) {
            impostaGiornoOrarioAperto(day, fasceOrarieConValori(day));
          }
        }
      });
    })();
  </script>
@endsection
