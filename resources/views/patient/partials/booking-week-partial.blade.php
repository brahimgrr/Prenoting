@php
  $weekPartialUrl = $weekPartialUrl ?? null;
  $periods = ['all' => 'Tutti', 'mattina' => 'Mattina', 'pomeriggio' => 'Pomeriggio'];

  $baseParams = array_filter(['service_id' => $selectedService?->id]);
  if (! empty($selectedPeriod) && $selectedPeriod !== 'all') {
    $baseParams['period'] = $selectedPeriod;
  }

  $buildUrl = function (string $base, array $extra, ?string $fragment = null) use ($baseParams): string {
    $params = array_filter(
      array_merge($baseParams, $extra),
      fn ($v) => $v !== null && $v !== ''
    );

    $url = $params ? $base.'?'.http_build_query($params) : $base;

    return $fragment ? $url.'#'.$fragment : $url;
  };

  $prevPageUrl = $previousWeekStart
    ? $buildUrl($baseUrl, ['week_start' => $previousWeekStart->toDateString()], 'booking-step-day')
    : null;
  $nextPageUrl = $nextWeekStart
    ? $buildUrl($baseUrl, ['week_start' => $nextWeekStart->toDateString()], 'booking-step-day')
    : null;

  $prevPartialUrl = $weekPartialUrl && $previousWeekStart
    ? $buildUrl($weekPartialUrl, ['week_start' => $previousWeekStart->toDateString()])
    : null;
  $nextPartialUrl = $weekPartialUrl && $nextWeekStart
    ? $buildUrl($weekPartialUrl, ['week_start' => $nextWeekStart->toDateString()])
    : null;

  $currentDate = $selectedDate ?? $weekStart->toDateString();
@endphp

<section id="booking-step-day" class="portal-panel booking-step d-grid gap-3 p-4">
  <div class="section-heading d-flex align-items-center justify-content-between gap-3">
    <h2>2. Scegli il giorno</h2>
  </div>

  <div class="week-strip-wrapper d-flex align-items-center gap-2">
    @if ($prevPageUrl && $prevPartialUrl)
      <a class="btn btn-outline-secondary week-nav-arrow flex-shrink-0 px-2"
        href="{{ $prevPageUrl }}"
        data-week-url="{{ $prevPartialUrl }}"
        data-page-url="{{ $prevPageUrl }}"
        aria-label="Settimana precedente">‹</a>
    @endif

    <div class="week-strip d-flex gap-2 flex-fill overflow-auto p-1">
      @foreach ($weekDays as $day)
        @php
          $date = $day['date'];
          $dateStr = $date->toDateString();
          $isSelectedDate = $selectedDate === $dateStr;
          $dayClasses = 'week-day d-flex flex-column align-items-center justify-content-center gap-1 text-center p-2'.($isSelectedDate ? ' week-day--selected border-primary bg-primary bg-opacity-10' : '').($day['hasSlots'] ? '' : ' week-day--disabled');
          $dayPageUrl = $buildUrl($baseUrl, ['week_start' => $weekStart->toDateString(), 'date' => $dateStr], 'booking-step-day');
        @endphp
        @if ($day['hasSlots'])
          <a class="{{ $dayClasses }}" href="{{ $dayPageUrl }}" data-date="{{ $dateStr }}">
        @else
          <div class="{{ $dayClasses }}">
        @endif
            <span class="week-day__name">{{ ucfirst($date->locale('it')->isoFormat('ddd')) }}</span>
            <strong>{{ $date->format('d') }}</strong>
            <span class="week-day__month">{{ ucfirst($date->locale('it')->isoFormat('MMM')) }}</span>
            <span class="availability-dot {{ $day['hasSlots'] ? 'availability-dot--open' : 'availability-dot--closed' }}"></span>
        @if ($day['hasSlots'])
          </a>
        @else
          </div>
        @endif
      @endforeach
    </div>

    @if ($nextPageUrl && $nextPartialUrl)
      <a class="btn btn-outline-secondary week-nav-arrow flex-shrink-0 px-2"
        href="{{ $nextPageUrl }}"
        data-week-url="{{ $nextPartialUrl }}"
        data-page-url="{{ $nextPageUrl }}"
        aria-label="Settimana successiva">›</a>
    @endif
  </div>
</section>

@if ($weekDays->isEmpty())
  <section class="slot-day-block portal-panel booking-step d-grid gap-3 p-4">
    <div class="section-heading d-flex align-items-center justify-content-between gap-3">
      <div>
        <h2>3. Scegli l'orario</h2>
        <span>Nessuna disponibilita trovata</span>
      </div>
    </div>
    <div class="empty-state">
      <h3>Nessuna disponibilita per questa prestazione</h3>
      <p>Prova un'altra prestazione o torna piu tardi quando il medico pubblichera nuovi slot.</p>
    </div>
  </section>
@else
  @foreach ($weekDays as $day)
    @php
      $dateStr = $day['date']->toDateString();
      $daySlots = $weekSlots[$dateStr] ?? collect();
      $isVisible = $selectedDate === $dateStr;
    @endphp
    <section class="slot-day-block portal-panel booking-step d-grid gap-3 p-4 {{ $isVisible ? '' : 'd-none' }}" data-date="{{ $dateStr }}">
      @if ($day['hasSlots'])
        <div class="section-heading d-flex align-items-center justify-content-between gap-3">
          <div>
            <h2>3. Scegli l'orario</h2>
            <span>{{ ucfirst($day['date']->locale('it')->isoFormat('dddd D MMMM')) }} · {{ $daySlots->count() }} slot liberi</span>
          </div>
        </div>

        <div class="slot-period-filter d-flex flex-wrap gap-2" role="group" aria-label="Filtra orari">
          @foreach ($periods as $period => $label)
            @php
              $periodUrl = $buildUrl($baseUrl, [
                'period' => $period === 'all' ? null : $period,
                'week_start' => $weekStart->toDateString(),
                'date' => $currentDate,
                'slot_start' => null,
              ], 'booking-step-day');
            @endphp
            <a
              class="btn btn-sm {{ $selectedPeriod === $period ? 'btn-primary' : 'btn-outline-primary' }}"
              href="{{ $periodUrl }}"
              data-slot-period-filter="{{ $period }}"
            >
              {{ $label }}
            </a>
          @endforeach
        </div>

        @if ($daySlots->isNotEmpty())
          <div class="row g-2 slot-choice-grid">
            @foreach ($daySlots as $slot)
              @php
                $slotPeriod = $slot->start_at->hour < 13 ? 'mattina' : 'pomeriggio';
                $isSelectedSlot = ($selectedSlot ?? null)?->key === $slot->key;
                $slotPageUrl = $buildUrl($baseUrl, [
                  'week_start' => $weekStart->toDateString(),
                  'date' => $dateStr,
                  'slot_start' => $slot->key,
                ], 'booking-confirm');
              @endphp
              <div class="col-4 col-md-3 col-xl-2 slot-choice-col" data-period="{{ $slotPeriod }}">
                <a
                  class="slot-time-button d-grid align-items-center justify-content-center w-100 p-2 {{ $isSelectedSlot ? 'slot-time-button--selected border-primary bg-primary bg-opacity-10' : '' }}"
                  href="{{ $slotPageUrl }}"
                >
                  <span>{{ $slot->start_at->format('H:i') }}</span>
                </a>
              </div>
            @endforeach
          </div>
        @else
          <div class="empty-state">
            <h3>Nessuno slot disponibile</h3>
            <p>Prova un altro filtro o scegli un altro giorno disponibile.</p>
          </div>
        @endif
      @else
        <div class="section-heading d-flex align-items-center justify-content-between gap-3">
          <div>
            <h2>3. Scegli l'orario</h2>
            <span>{{ ucfirst($day['date']->locale('it')->isoFormat('dddd D MMMM')) }}</span>
          </div>
        </div>
        <div class="empty-state">
          <h3>Nessuno slot disponibile</h3>
          <p>Scegli un altro giorno o passa alla settimana successiva.</p>
        </div>
      @endif
    </section>
  @endforeach
@endif

