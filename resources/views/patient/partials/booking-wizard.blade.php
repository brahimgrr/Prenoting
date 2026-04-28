@php
  $queryUrl = function (array $params, ?string $fragment = null): string {
    $query = array_merge(request()->query(), $params);
    $query = array_filter($query, fn ($value) => $value !== null && $value !== '');
    $url = url()->current().($query ? '?'.http_build_query($query) : '');

    return $fragment ? $url.'#'.$fragment : $url;
  };
  $prevWeek = $weekStart->subWeek();
  $nextWeek = $weekStart->addWeek();
  $prevWeekUrl = $queryUrl([
    'week_start' => $prevWeek->toDateString(),
    'month' => $prevWeek->startOfMonth()->format('Y-m'),
    'date' => null,
    'slot_id' => null,
  ], 'booking-step-day');
  $nextWeekUrl = $queryUrl([
    'week_start' => $nextWeek->toDateString(),
    'month' => $nextWeek->startOfMonth()->format('Y-m'),
    'date' => null,
    'slot_id' => null,
  ], 'booking-step-day');
  $monthOptions = $availableMonths
    ->concat([$visibleMonth])
    ->unique(fn ($month) => $month->format('Y-m'))
    ->sortBy(fn ($month) => $month->format('Y-m'))
    ->values();
  $periods = [
    'all' => 'Tutti',
    'mattina' => 'Mattina',
    'pomeriggio' => 'Pomeriggio',
  ];
@endphp

<div class="booking-wizard">
  <section id="booking-step-service" class="portal-panel booking-step">
    <div class="section-heading">
      <h2>1. Scegli la prestazione</h2>
      <span>{{ $services->count() }} disponibili</span>
    </div>
    <div class="service-card-grid">
      @foreach ($services as $service)
        @php
          $isSelected = $selectedService?->id === $service->id;
          $cardClasses = 'service-choice-card'.($isSelected ? ' border-primary bg-primary bg-opacity-10' : '');
          $href = $isReschedule ? null : $queryUrl([
            'service_id' => $service->id,
            'month' => null,
            'week_start' => null,
            'date' => null,
            'slot_id' => null,
          ], 'booking-step-day');
        @endphp
        @if ($href)
          <a class="{{ $cardClasses }}" href="{{ $href }}">
        @else
          <div class="{{ $cardClasses }} {{ $isSelected ? '' : 'service-choice-card--disabled' }}">
        @endif
            <span class="service-choice-card__name">{{ $service->name }}</span>
            <span class="service-choice-card__meta">
              {{ $service->specialty?->name ?? $service->category }} · {{ $service->duration_minutes }} min
            </span>
            @if ($service->price)
              <span class="service-choice-card__price">€ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
            @endif
        @if ($href)
          </a>
        @else
          </div>
        @endif
      @endforeach
    </div>
  </section>

  @if ($selectedService)
    <section id="booking-step-day" class="portal-panel booking-step">
      <div class="section-heading booking-month-heading">
        <div>
          <h2>2. Scegli il giorno</h2>
          <span>Mese visualizzato</span>
          <strong>{{ ucfirst($visibleMonth->locale('it')->isoFormat('MMMM YYYY')) }}</strong>
        </div>
        <div class="month-nav">
          <select class="form-select form-select-sm month-jump-select" aria-label="Salta a un mese disponibile">
            @foreach ($monthOptions as $month)
              <option
                value="{{ $queryUrl(['month' => $month->format('Y-m'), 'week_start' => null, 'date' => null, 'slot_id' => null], 'booking-step-day') }}"
                {{ $month->format('Y-m') === $visibleMonth->format('Y-m') ? 'selected' : '' }}
              >
                {{ ucfirst($month->locale('it')->isoFormat('MMMM YYYY')) }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="week-nav">
        <a class="btn btn-sm btn-outline-secondary" href="{{ $prevWeekUrl }}">Settimana prima</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $nextWeekUrl }}">Settimana dopo</a>
      </div>

      <div class="week-strip">
        @foreach ($weekDays as $day)
          @php
            $date = $day['date'];
            $isSelectedDate = $selectedDate === $date->toDateString();
            $dayClasses = 'week-day'.($isSelectedDate ? ' week-day--selected' : '').($day['hasSlots'] ? '' : ' week-day--disabled');
          @endphp
          @if ($day['hasSlots'])
            <a class="{{ $dayClasses }}" href="{{ $queryUrl([
              'date' => $date->toDateString(),
              'week_start' => $weekStart->toDateString(),
              'month' => $visibleMonth->format('Y-m'),
              'slot_id' => null,
            ], 'booking-step-slots') }}">
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
    </section>
  @endif

  @if ($selectedService && $selectedDate)
    <section id="booking-step-slots" class="portal-panel booking-step">
      <div class="section-heading">
        <div>
          <h2>3. Scegli l'orario</h2>
          <span>{{ \Carbon\CarbonImmutable::parse($selectedDate)->locale('it')->isoFormat('dddd D MMMM') }} · {{ $slots->count() }} slot liberi</span>
        </div>
      </div>

      <div class="slot-period-filter" role="group" aria-label="Filtra orari">
        @foreach ($periods as $period => $label)
          <button type="button" class="btn btn-sm {{ $period === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" data-slot-period-filter="{{ $period }}">
            {{ $label }}
          </button>
        @endforeach
      </div>

      @if ($slots->isNotEmpty())
        <div class="row g-2 slot-choice-grid">
          @foreach ($slots as $slot)
            @php
              $period = $slot->start_at->hour < 13 ? 'mattina' : 'pomeriggio';
              $isSelectedSlot = $selectedSlot?->id === $slot->id;
            @endphp
            <div class="col-4 col-md-3 col-xl-2 slot-choice-col" data-period="{{ $period }}">
              <a
                class="slot-time-button {{ $isSelectedSlot ? 'slot-time-button--selected' : '' }}"
                href="{{ $queryUrl([
                  'date' => $slot->start_at->toDateString(),
                  'week_start' => $weekStart->toDateString(),
                  'month' => $visibleMonth->format('Y-m'),
                  'slot_id' => $slot->id,
                ], 'booking-confirm') }}"
              >
                <span>{{ $slot->start_at->format('H:i') }}</span>
                <small>{{ $slot->doctor?->display_name ?? 'Medico' }}</small>
              </a>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessuno slot disponibile</h3>
          <p>Scegli un altro giorno o passa alla settimana successiva.</p>
        </div>
      @endif
    </section>
  @endif

  @if ($selectedService && $selectedSlot)
    @php
      $cancelSelectionUrl = $isReschedule
        ? $queryUrl(['month' => null, 'week_start' => null, 'date' => null, 'slot_id' => null], 'booking-step-service')
        : $queryUrl(['service_id' => null, 'month' => null, 'week_start' => null, 'date' => null, 'slot_id' => null], 'booking-step-service');
    @endphp
    <section id="booking-confirm" class="portal-panel booking-step booking-confirm-panel">
      <div class="section-heading">
        <div>
          <h2>{{ $isReschedule ? 'Conferma spostamento' : 'Conferma prenotazione' }}</h2>
          <span>Controlla i dettagli prima di inviare</span>
        </div>
      </div>

      <dl class="booking-confirm-summary">
        <div>
          <dt>Prestazione</dt>
          <dd>{{ $selectedService->name }}</dd>
        </div>
        <div>
          <dt>Data e ora</dt>
          <dd>{{ $selectedSlot->start_at->format('d/m/Y H:i') }}</dd>
        </div>
        <div>
          <dt>Medico</dt>
          <dd>{{ $selectedSlot->doctor?->display_name ?? 'Medico' }}</dd>
        </div>
        <div>
          <dt>Ambulatorio</dt>
          <dd>{{ $selectedSlot->clinic?->name ?? 'Ambulatorio #'.$selectedSlot->clinic_id }}</dd>
        </div>
      </dl>

      <form method="POST" action="{{ $formAction }}" class="booking-confirm-form">
        @csrf
        @if (! $isReschedule)
          <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
          <label class="form-label">
            Note opzionali
            <textarea class="form-control" name="notes" rows="3" placeholder="Aggiungi indicazioni utili per il medico"></textarea>
          </label>
        @endif
        <input type="hidden" name="slot_id" value="{{ $selectedSlot->id }}">
        <button type="submit" class="btn btn-primary">
          {{ $isReschedule ? 'Conferma spostamento' : 'Conferma prenotazione' }}
        </button>
        <a class="btn btn-outline-secondary" href="{{ $cancelSelectionUrl }}">
          {{ $isReschedule ? 'Annulla spostamento' : 'Annulla prenotazione' }}
        </a>
      </form>
    </section>
  @endif
</div>
