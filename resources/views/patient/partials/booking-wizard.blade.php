@php
  $queryUrl = function (array $params): string {
    $query = array_merge(request()->query(), $params);
    $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

    return url()->current().($query ? '?'.http_build_query($query) : '');
  };
  $prevWeekUrl = $queryUrl(['week_start' => $weekStart->subWeek()->toDateString(), 'date' => null]);
  $nextWeekUrl = $queryUrl(['week_start' => $weekStart->addWeek()->toDateString(), 'date' => null]);
  $periods = [
    'all' => 'Tutti',
    'mattina' => 'Mattina',
    'pomeriggio' => 'Pomeriggio',
  ];
@endphp

<div class="booking-wizard">
  <section class="portal-panel booking-step">
    <div class="section-heading">
      <h2>1. Scegli la prestazione</h2>
      <span>{{ $services->count() }} disponibili</span>
    </div>
    <div class="service-card-grid">
      @foreach ($services as $service)
        @php
          $isSelected = $selectedService?->id === $service->id;
          $cardClasses = 'service-choice-card'.($isSelected ? ' border-primary bg-primary bg-opacity-10' : '');
          $href = $isReschedule ? null : $queryUrl(['service_id' => $service->id, 'date' => null]);
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
    <section class="portal-panel booking-step">
      <div class="section-heading">
        <h2>2. Scegli il giorno</h2>
        <div class="week-nav">
          <a class="btn btn-sm btn-outline-secondary" href="{{ $prevWeekUrl }}">Settimana prima</a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ $nextWeekUrl }}">Settimana dopo</a>
        </div>
      </div>
      <div class="week-strip">
        @foreach ($weekDays as $day)
          @php
            $date = $day['date'];
            $isSelectedDate = $selectedDate === $date->toDateString();
            $dayClasses = 'week-day'.($isSelectedDate ? ' week-day--selected' : '').($day['hasSlots'] ? '' : ' week-day--disabled');
          @endphp
          @if ($day['hasSlots'])
            <a class="{{ $dayClasses }}" href="{{ $queryUrl(['date' => $date->toDateString(), 'week_start' => $weekStart->toDateString()]) }}">
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
    <section class="portal-panel booking-step">
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
            @endphp
            <div class="col-4 col-md-3 col-xl-2 slot-choice-col" data-period="{{ $period }}">
              <form method="POST" action="{{ $formAction }}">
                @csrf
                <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                <button type="submit" class="slot-time-button">
                  <span>{{ $slot->start_at->format('H:i') }}</span>
                  <small>{{ $slot->doctor?->display_name ?? 'Medico' }}</small>
                </button>
              </form>
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
</div>

<script>
  document.querySelectorAll('[data-slot-period-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
      var period = button.getAttribute('data-slot-period-filter');
      document.querySelectorAll('[data-slot-period-filter]').forEach(function (item) {
        item.classList.toggle('btn-primary', item === button);
        item.classList.toggle('btn-outline-primary', item !== button);
      });
      document.querySelectorAll('.slot-choice-col').forEach(function (slot) {
        slot.classList.toggle('d-none', period !== 'all' && slot.getAttribute('data-period') !== period);
      });
    });
  });
</script>
