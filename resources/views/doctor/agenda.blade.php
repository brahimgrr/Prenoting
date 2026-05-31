@extends('layouts.portal', ['title' => 'Agenda - MedPortal'])

@section('content')
  <section class="portal-section operations-dashboard container-xxl">

    <div class="portal-page-heading portal-heading-row d-flex align-items-center justify-content-between gap-3 mb-4">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Agenda</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#closureCreateModal">
          Chiusura extra
        </button>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#specialOpeningCreateModal">
          Apertura extra
        </button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Appuntamenti</span>
          <strong class="d-block fs-2 lh-1 mt-2">{{ $appointments->count() }}</strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Slot disponibili</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-success">
            {{ $daySlots->count() }}
          </strong>
        </section>
      </div>
      <div class="col-4">
        <section class="portal-panel h-100 p-3">
          <span class="d-block text-body-secondary small fw-bold text-uppercase">Fatturato Previsto</span>
          <strong class="d-block fs-2 lh-1 mt-2 text-primary">
            &euro; {{ number_format((float) $fatturato, 2, ',', '.') }}
          </strong>
        </section>
      </div>
    </div>
<section class="portal-panel doctor-agenda-panel p-4 mb-3">
      <div class="week-strip-wrapper d-flex align-items-center gap-2 mb-3">
        <a class="btn btn-outline-secondary week-nav-arrow flex-shrink-0 px-2"
           href="/doctor/agenda?date={{ $previousWeekStart->toDateString() }}&week_start={{ $previousWeekStart->toDateString() }}">&#8249;</a>

        <div class="week-strip week-strip--agenda d-flex gap-2 flex-fill overflow-auto p-1">
          @foreach ($weekDays as $weekDay)
            @php
              $dayDateStr = $weekDay['date']->toDateString();
              $isSelected = $dayDateStr === $date;
              $dayClass   = 'week-day d-flex flex-column align-items-center justify-content-center gap-1 text-center p-2' . ($isSelected ? ' week-day--selected border-primary bg-primary bg-opacity-10' : '');
            @endphp
            <a class="{{ $dayClass }}"
               href="/doctor/agenda?date={{ $dayDateStr }}&week_start={{ $weekStart->toDateString() }}">
              <span class="week-day__name">{{ ucfirst($weekDay['date']->locale('it')->isoFormat('ddd')) }}</span>
              <strong>{{ $weekDay['date']->format('d') }}</strong>
              <span class="week-day__month">{{ ucfirst($weekDay['date']->locale('it')->isoFormat('MMM')) }}</span>
              <span class="availability-dot availability-dot--{{ $weekDay['availabilityState'] }}"></span>
            </a>
          @endforeach
        </div>

        <a class="btn btn-outline-secondary week-nav-arrow flex-shrink-0 px-2"
           href="/doctor/agenda?date={{ $nextWeekStart->toDateString() }}&week_start={{ $nextWeekStart->toDateString() }}">&#8250;</a>
      </div>

      <div class="doctor-agenda-scroll" data-agenda-scroll-container>
        <div class="doctor-agenda-grid">
          @foreach ($agendaRows as $row)
            @php
              $rowClasses = 'doctor-agenda-row' . ($row['is_past'] ? ' doctor-agenda-row--past' : '');
            @endphp
            <div class="{{ $rowClasses }}" data-time="{{ $row['label'] }}" @if ($row['items']->isNotEmpty()) data-agenda-occupied-row @endif>
              <time class="doctor-agenda-row__time" datetime="{{ $row['start_at']->toIso8601String() }}">
                {{ $row['label'] }}
              </time>

              @if ($row['items']->isEmpty() && ! $row['is_current'])
                <div class="doctor-agenda-row__content"></div>
              @else
                <div class="doctor-agenda-row__content">
                  @if ($row['is_current'])
                    <div class="doctor-agenda-now-marker" data-agenda-now-marker style="--now-position: {{ $row['now_position'] }}%;">
                      <span>Ora {{ $currentTime->format('H:i') }}</span>
                    </div>
                  @endif

                  @foreach ($row['items'] as $item)
                    @php
                      $appointment      = $item['appointment'];
                      $slot             = $item['slot'];
                      $closure          = $item['closure'];
                      $state            = $item['state'];
                      $start            = $item['start_at'];
                      $hasStarted       = $start->lessThanOrEqualTo($currentTime);
                      $showPassatoBadge = $hasStarted && ! $isSelectedToday;
                      $itemClass        = $item['type'] === 'appointment' ? 'appointment' : $state;
                      $spanRows         = $item['span_rows'] ?? 1;
                      $itemClasses      = 'doctor-agenda-item doctor-agenda-item--'.$itemClass.($spanRows > 1 ? ' doctor-agenda-item--spanning' : '');
                    @endphp

                    <article class="{{ $itemClasses }}"
                      @if ($closure)
                        data-agenda-closure-id="{{ $closure->id }}"
                        style="--agenda-span-rows: {{ $spanRows }};"
                      @endif
                    >
                      @if ($appointment)
                        @php
                          $appointmentCancelModalId = "appointmentCancelModal{$appointment->id}";
                          $canCancelAppointment = in_array($appointment->status, \App\Models\Appointment::ACTIVE_SLOT_STATUSES, true)
                            && $appointment->start_at->isFuture();
                        @endphp
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main d-flex align-items-center justify-content-between gap-3">
                            <div>
                              <h3>{{ $appointment->patientName() }}</h3>
                              <p>{{ $appointment->service?->name ?? 'Appuntamento' }}</p>
                            </div>
                            <div class="doctor-agenda-item__actions d-flex align-items-center justify-content-end gap-2">
                              <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#appointmentInfoModal{{ $appointment->id }}"
                              ><i class="bi bi-info-circle"></i></button>
                              @if ($canCancelAppointment)
                                <button
                                  class="btn btn-sm btn-outline-danger"
                                  type="button"
                                  data-bs-toggle="modal"
                                  data-bs-target="#{{ $appointmentCancelModalId }}"
                                  title="Annulla appuntamento"
                                ><i class="bi bi-trash"></i></button>
                              @endif
                            </div>
                          </div>
                        </div>
                        @push('modals')
                          @include('doctor.partials.appointment-info-modal', ['appointment' => $appointment])
                          @if ($canCancelAppointment)
                            <x-appointment-cancel-modal
                              :appointment="$appointment"
                              :action="'/doctor/appointments/'.$appointment->id.'/cancel'"
                              :modal-id="$appointmentCancelModalId"
                            />
                          @endif
                        @endpush
                      @else
                        @php
                          $slotTitle = $closure
                            ? ($closure->reason ?: 'Chiusura')
                            : 'Slot libero';
                          $closureRange = null;
                          if ($closure) {
                            $closureRange = $closure->start_time
                              ? ($item['closure_start_at']->format('H:i').' - '.$item['closure_end_at']->format('H:i'))
                              : 'Tutto il giorno';
                          }
                        @endphp
                        <div class="doctor-agenda-item__body">
                          <div class="doctor-agenda-item__main d-flex align-items-center justify-content-between gap-3">
                            <div>
                              <h3>{{ $slotTitle }}</h3>
                              @if ($closureRange)
                                <p>{{ $closureRange }}</p>
                              @endif
                            </div>
                            <div class="doctor-agenda-item__actions d-flex align-items-center justify-content-end gap-2">
                              @if ($showPassatoBadge)
                                <span class="badge badge-neutral">Passato</span>
                              @elseif ($state === 'blocked' && ! $hasStarted && $closure)
                                <form method="POST" action="/doctor/closures/{{ $closure->id }}">
                                  @csrf
                                  @method('DELETE')
                                  <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Riapri disponibilita"
                                  ><i class="bi bi-unlock"></i></button>
                                </form>
                              @elseif ($state === 'free' && ! $hasStarted)
                                <form method="POST" action="/doctor/availability/block">
                                  @csrf
                                  <input type="hidden" name="slot_start" value="{{ $slot->key }}">
                                  <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Blocca slot libero"
                                  ><i class="bi bi-slash-circle"></i></button>
                                </form>
                              @endif
                            </div>
                          </div>
                        </div>
                      @endif
                    </article>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="portal-panel schedule-events-panel p-4 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 mb-0">Prossimi eventi</h2>
      </div>

      @if ($upcomingScheduleEvents->isEmpty())
        <p class="text-body-secondary mb-0">Nessun evento programmato.</p>
      @else
        <div class="schedule-event-list">
          @foreach ($upcomingScheduleEvents as $event)
            @php
              $model = $event['model'];
              $deleteModalId = $event['type'] === 'closure'
                ? "deleteScheduleEventModalClosure{$model->id}"
                : "deleteScheduleEventModalSpecialOpening{$model->id}";
              $timeLabel = $event['start_time']
                ? "{$event['start_time']} - {$event['end_time']}"
                : 'Tutto il giorno';
              $eventTypeClass = $event['type'] === 'closure' ? 'closure' : 'special-opening';
            @endphp
            <article class="schedule-event-card schedule-event-card--{{ $eventTypeClass }}">
              <div class="schedule-event-row__content">
                <h3>{{ $event['title'] }}</h3>
                <p>{{ ucfirst($event['date']->locale('it')->isoFormat('ddd D MMM')) }} &middot; {{ $timeLabel }}</p>
              </div>
              <div class="schedule-event-row__actions" role="group">
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#{{ $deleteModalId }}">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </section>



  </section>

  <div class="modal fade" id="closureCreateModal" tabindex="-1">
    @php
      $closureAllDay = old('all_day') !== null
        ? (bool) old('all_day')
        : blank(old('start_time')) && blank(old('end_time'));
      $closureStartTime = $closureAllDay ? '' : old('start_time');
      $closureEndTime = $closureAllDay ? '' : old('end_time');
    @endphp
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/doctor/closures">
          @csrf
          <div class="modal-header">
            <h2 class="modal-title h5" id="closureCreateModalLabel">Chiusura</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            @error('closure') <div class="alert alert-danger">{{ $message }}</div> @enderror
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="closure-date">Dal</label>
                <input class="form-control" id="closure-date" type="date" name="date" value="{{ old('date', $date) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-end-date">Al</label>
                <input class="form-control" id="closure-end-date" type="date" name="end_date" value="{{ old('end_date', $date) }}">
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" id="closure-all-day" type="checkbox" name="all_day" value="1" data-closure-all-day-toggle @checked($closureAllDay)>
                  <label class="form-check-label" for="closure-all-day">Tutto il giorno</label>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-start-time">Ora inizio</label>
                <x-time-select class="form-control" id="closure-start-time" name="start_time" :value="$closureStartTime" data-closure-time-field :disabled="$closureAllDay" />
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="closure-end-time">Ora fine</label>
                <x-time-select class="form-control" id="closure-end-time" name="end_time" :value="$closureEndTime" :include-end-of-day="true" data-closure-time-field :disabled="$closureAllDay" />
              </div>
              <div class="col-12">
                <label class="form-label" for="closure-reason">Motivo</label>
                <input class="form-control" id="closure-reason" name="reason" value="{{ old('reason') }}" placeholder="Ferie, congresso, riunione">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-danger">Salva chiusura</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="specialOpeningCreateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/doctor/special-openings">
          @csrf
          <div class="modal-header">
            <h2 class="modal-title h5" id="specialOpeningCreateModalLabel">Apertura extra</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            @error('special_opening') <div class="alert alert-danger">{{ $message }}</div> @enderror
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="special-opening-date">Data</label>
                <input class="form-control" id="special-opening-date" type="date" name="date" value="{{ old('date', $date) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="special-opening-start">Ora inizio</label>
                <x-time-select class="form-control" id="special-opening-start" name="start_time" :value="old('start_time', '09:00')" required />
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="special-opening-end">Ora fine</label>
                <x-time-select class="form-control" id="special-opening-end" name="end_time" :value="old('end_time', '12:00')" :include-end-of-day="true" required />
              </div>
              <div class="col-12">
                <label class="form-label" for="special-opening-note">Nota</label>
                <input class="form-control" id="special-opening-note" name="note" value="{{ old('note') }}" placeholder="Open day, recupero visite">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-primary">Salva apertura</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @foreach ($upcomingScheduleEvents as $event)
    @php
      $model = $event['model'];
      $deleteModalId = $event['type'] === 'closure'
        ? "deleteScheduleEventModalClosure{$model->id}"
        : "deleteScheduleEventModalSpecialOpening{$model->id}";
      $deleteAction = $event['type'] === 'closure'
        ? "/doctor/closures/{$model->id}"
        : "/doctor/special-openings/{$model->id}";
      $deleteMessage = $event['type'] === 'closure'
        ? 'Questa chiusura verrà eliminata.'
        : 'Questa apertura extra verrà eliminata.';
    @endphp
    <div class="modal fade" id="{{ $deleteModalId }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="{{ $deleteAction }}">
            @csrf
            @method('DELETE')
            <div class="modal-header">
              <h2 class="modal-title fs-5" id="{{ $deleteModalId }}Label">Conferma eliminazione</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p>{{ $deleteMessage }}</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
              <button type="submit" class="btn btn-danger">Sì, elimina</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endforeach

  <script>
    (() => {
      function posizionaScrollAgenda() {
        const container = document.querySelector("[data-agenda-scroll-container]");
        const marker = container?.querySelector("[data-agenda-now-marker]");
        const firstOccupiedRow = container?.querySelector("[data-agenda-occupied-row]");
        if (!container || (!marker && !firstOccupiedRow)) return;

        requestAnimationFrame(() => {
          const containerRect = container.getBoundingClientRect();
          const targetRect = (marker ?? firstOccupiedRow).getBoundingClientRect();
          const targetTop = targetRect.top - containerRect.top + container.scrollTop;

          if (marker) {
            const markerCenter = targetTop + targetRect.height / 2;
            container.scrollTop = Math.max(0, markerCenter - container.clientHeight / 2);
            return;
          }

          container.scrollTop = Math.max(0, targetTop);
        });
      }

      function aggiornaIndicatoreOraAgenda() {
        const container = document.querySelector("[data-agenda-scroll-container]");
        if (!container) return;

        const now = new Date();
        const rows = Array.from(container.querySelectorAll(".doctor-agenda-row"));

        let currentRow = null;
        rows.forEach((row) => {
          const timeEl = row.querySelector("time[datetime]");
          if (!timeEl) return;
          const rowStart = new Date(timeEl.getAttribute("datetime"));
          const rowEnd = new Date(rowStart.getTime() + 30 * 60 * 1000);
          row.classList.toggle("doctor-agenda-row--past", now >= rowEnd);
          if (now >= rowStart && now < rowEnd) currentRow = row;
        });

        let marker = container.querySelector("[data-agenda-now-marker]");

        if (!currentRow) {
          marker?.remove();
          return;
        }

        const contentDiv = currentRow.querySelector(".doctor-agenda-row__content");
        if (!contentDiv) return;

        if (!marker) {
          marker = document.createElement("div");
          marker.className = "doctor-agenda-now-marker";
          marker.setAttribute("data-agenda-now-marker", "");
          marker.innerHTML = "<span></span>";
          contentDiv.prepend(marker);
        } else if (!contentDiv.contains(marker)) {
          const oldContent = marker.parentElement;
          marker.remove();
          contentDiv.prepend(marker);
        }

        const rowStart = new Date(currentRow.querySelector("time[datetime]").getAttribute("datetime"));
        const minutesIntoRow = (now - rowStart) / 60000;
        marker.style.setProperty("--now-position", `${Math.min(100, Math.max(0, (minutesIntoRow / 30) * 100))}%`);

        const label = marker.querySelector("span");
        if (label) {
          const hh = String(now.getHours()).padStart(2, "0");
          const mm = String(now.getMinutes()).padStart(2, "0");
          label.textContent = `Ora ${hh}:${mm}`;
        }
      }

      function sincronizzaCampiOrarioChiusura() {
        const allDayToggle = document.querySelector("[data-closure-all-day-toggle]");
        const timeFields = Array.from(document.querySelectorAll("[data-closure-time-field]"));
        if (!allDayToggle || timeFields.length === 0) return;

        const applicaStatoTuttoIlGiorno = () => {
          timeFields.forEach((field) => {
            field.disabled = allDayToggle.checked;
            if (allDayToggle.checked) field.value = "";
          });
        };

        allDayToggle.addEventListener("change", applicaStatoTuttoIlGiorno);
        timeFields.forEach((field) => {
          field.addEventListener("change", () => {
            if (field.value === "") return;
            allDayToggle.checked = false;
            applicaStatoTuttoIlGiorno();
          });
        });

        applicaStatoTuttoIlGiorno();
      }

      posizionaScrollAgenda();
      aggiornaIndicatoreOraAgenda();
      sincronizzaCampiOrarioChiusura();

      if (document.querySelector("[data-agenda-scroll-container]")) {
        setInterval(aggiornaIndicatoreOraAgenda, 30_000);
      }
    })();
  </script>
@endsection
