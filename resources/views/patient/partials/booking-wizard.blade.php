@php
  $queryUrl = function (array $params, ?string $fragment = null) use ($baseUrl): string {
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    $url = $params ? $baseUrl.'?'.http_build_query($params) : $baseUrl;

    return $fragment ? $url.'#'.$fragment : $url;
  };
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
    <div id="booking-week-region">
      @include('patient.partials.booking-week-partial')
    </div>
  @endif

  @if ($selectedService && $selectedSlot)
    @php
      $cancelSelectionUrl = $isReschedule
        ? $queryUrl(['date' => null, 'slot_id' => null], 'booking-step-service')
        : $queryUrl(['service_id' => null, 'date' => null, 'slot_id' => null], 'booking-step-service');
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
