@php
  $weekPartialUrl = $weekPartialUrl ?? null;
  $periods = ['all' => 'Tutti', 'mattina' => 'Mattina', 'pomeriggio' => 'Pomeriggio'];

  $prevWeek = $weekStart->subWeek();
  $nextWeek = $weekStart->addWeek();

  $baseParams = array_filter(['service_id' => $selectedService?->id]);
  if (! empty($selectedPeriod) && $selectedPeriod !== 'all') {
    $baseParams['period'] = $selectedPeriod;
  }

  $buildUrl = function (string $base, array $extra) use ($baseParams): string {
    $params = array_filter(
      array_merge($baseParams, $extra),
      fn ($v) => $v !== null && $v !== ''
    );

    return $params ? $base.'?'.http_build_query($params) : $base;
  };

  $prevPageUrl = $buildUrl($baseUrl, ['week_start' => $prevWeek->toDateString()]);
  $nextPageUrl = $buildUrl($baseUrl, ['week_start' => $nextWeek->toDateString()]);

  $prevPartialUrl = $weekPartialUrl
    ? $buildUrl($weekPartialUrl, ['week_start' => $prevWeek->toDateString()])
    : null;
  $nextPartialUrl = $weekPartialUrl
    ? $buildUrl($weekPartialUrl, ['week_start' => $nextWeek->toDateString()])
    : null;

  $monthOptions = $availableMonths
    ->concat([$visibleMonth])
    ->unique(fn ($month) => $month->format('Y-m'))
    ->sortBy(fn ($month) => $month->format('Y-m'))
    ->values();

  $currentDate = $selectedDate ?? $weekStart->toDateString();
@endphp

<section id="booking-step-day" class="portal-panel booking-step">
  <div class="section-heading booking-month-heading">
    <div>
      <h2>2. Scegli il giorno</h2>
      <span>Mese visualizzato</span>
      <strong>{{ ucfirst($visibleMonth->locale('it')->isoFormat('MMMM YYYY')) }}</strong>
    </div>
    <div class="month-nav">
      <select class="form-select form-select-sm month-jump-select" aria-label="Salta a un mese disponibile" onchange="window.location.href=this.value">
        @foreach ($monthOptions as $month)
          <option
            value="{{ $buildUrl($baseUrl, ['month' => $month->format('Y-m'), 'week_start' => null, 'date' => null, 'slot_id' => null]) }}"
            {{ $month->format('Y-m') === $visibleMonth->format('Y-m') ? 'selected' : '' }}
          >
            {{ ucfirst($month->locale('it')->isoFormat('MMMM YYYY')) }}
          </option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="week-strip-wrapper">
    @if ($prevPartialUrl)
      <a class="btn btn-outline-secondary week-nav-arrow"
        href="{{ $prevPageUrl }}"
        data-week-url="{{ $prevPartialUrl }}"
        data-page-url="{{ $prevPageUrl }}"
        aria-label="Settimana precedente">‹</a>
    @else
      <a class="btn btn-outline-secondary week-nav-arrow" href="{{ $prevPageUrl }}" aria-label="Settimana precedente">‹</a>
    @endif

    <div class="week-strip">
      @foreach ($weekDays as $day)
        @php
          $date = $day['date'];
          $dateStr = $date->toDateString();
          $isSelectedDate = $selectedDate === $dateStr;
          $dayClasses = 'week-day'.($isSelectedDate ? ' week-day--selected' : '').($day['hasSlots'] ? '' : ' week-day--disabled');
          $dayPageUrl = $buildUrl($baseUrl, ['week_start' => $weekStart->toDateString(), 'date' => $dateStr]);
        @endphp
        @if ($day['hasSlots'])
          <a class="{{ $dayClasses }}" href="{{ $dayPageUrl }}" data-date="{{ $dateStr }}">
        @else
          <div class="{{ $dayClasses }}">
        @endif
            <span class="week-day__name">{{ ucfirst($date->locale('it')->isoFormat('ddd')) }}</span>
            <strong>{{ $date->format('d') }}</strong>
            <span class="availability-dot {{ $day['hasSlots'] ? 'availability-dot--open' : 'availability-dot--closed' }}"></span>
        @if ($day['hasSlots'])
          </a>
        @else
          </div>
        @endif
      @endforeach
    </div>

    @if ($nextPartialUrl)
      <a class="btn btn-outline-secondary week-nav-arrow"
        href="{{ $nextPageUrl }}"
        data-week-url="{{ $nextPartialUrl }}"
        data-page-url="{{ $nextPageUrl }}"
        aria-label="Settimana successiva">›</a>
    @else
      <a class="btn btn-outline-secondary week-nav-arrow" href="{{ $nextPageUrl }}" aria-label="Settimana successiva">›</a>
    @endif
  </div>
</section>

@foreach ($weekDays as $day)
  @php
    $dateStr = $day['date']->toDateString();
    $daySlots = $weekSlots[$dateStr] ?? collect();
    $isVisible = $selectedDate === $dateStr;
  @endphp
  <section class="slot-day-block portal-panel booking-step {{ $isVisible ? '' : 'd-none' }}" data-date="{{ $dateStr }}">
    @if ($day['hasSlots'])
      <div class="section-heading">
        <div>
          <h2>3. Scegli l'orario</h2>
          <span>{{ ucfirst($day['date']->locale('it')->isoFormat('dddd D MMMM')) }} · {{ $daySlots->count() }} slot liberi</span>
        </div>
      </div>

      <div class="slot-period-filter" role="group" aria-label="Filtra orari">
        @foreach ($periods as $period => $label)
          @php
            $periodUrl = $buildUrl($baseUrl, [
              'period' => $period === 'all' ? null : $period,
              'week_start' => $weekStart->toDateString(),
              'date' => $currentDate,
              'slot_id' => null,
            ]);
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

      <div class="row g-2 slot-choice-grid">
        @foreach ($daySlots as $slot)
          @php
            $slotPeriod = $slot->start_at->hour < 13 ? 'mattina' : 'pomeriggio';
            $isSelectedSlot = ($selectedSlot ?? null)?->id === $slot->id;
            $slotPageUrl = $buildUrl($baseUrl, [
              'week_start' => $weekStart->toDateString(),
              'date' => $dateStr,
              'slot_id' => $slot->id,
            ]);
          @endphp
          <div class="col-4 col-md-3 col-xl-2 slot-choice-col" data-period="{{ $slotPeriod }}">
            <a
              class="slot-time-button {{ $isSelectedSlot ? 'slot-time-button--selected' : '' }}"
              href="{{ $slotPageUrl }}"
            >
              <span>{{ $slot->start_at->format('H:i') }}</span>
              <small>{{ $slot->doctor?->display_name ?? 'Medico' }}</small>
            </a>
          </div>
        @endforeach
      </div>
    @else
      <div class="section-heading">
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
