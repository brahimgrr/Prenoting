@php
  $queryUrl = function (array $params, ?string $fragment = null) use ($baseUrl): string {
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    $url = $params ? $baseUrl.'?'.http_build_query($params) : $baseUrl;

    return $fragment ? $url.'#'.$fragment : $url;
  };
@endphp

<div class="booking-wizard d-grid gap-3">
  <section id="booking-step-service" class="portal-panel booking-step d-grid gap-3 p-4">
    <div class="section-heading d-flex align-items-center justify-content-between gap-3">
      <h2>1. Scegli la prestazione</h2>
      <span>{{ $services->count() }} disponibili</span>
    </div>
    <div class="service-card-grid row g-3">
      @foreach ($services as $service)
        @php
          $isSelected = $selectedService?->id === $service->id;
          $cardClasses = 'service-choice-card d-grid gap-2 h-100 p-3'.($isSelected ? ' border-primary bg-primary bg-opacity-10' : '');
          $href = $isReschedule ? null : $queryUrl([
            'service_id' => $service->id,
          ], 'booking-step-day');
        @endphp
        <div class="col-12 col-md-6">
        @if ($href)
          <a class="{{ $cardClasses }}" href="{{ $href }}">
        @else
          <div class="{{ $cardClasses }} {{ $isSelected ? '' : 'service-choice-card--disabled' }}">
        @endif
            <span class="service-choice-card__name">{{ $service->name }}</span>
            <span class="service-choice-card__meta">
              {{ $service->category }} · {{ $service->duration_minutes }} min
            </span>
            @if ($service->price)
              <span class="service-choice-card__price">€ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
            @endif
        @if ($href)
          </a>
        @else
          </div>
        @endif
        </div>
      @endforeach
    </div>
  </section>

  @if ($selectedService)
    <div id="booking-week-region" class="d-grid gap-3">
      @include('patient.partials.booking-week-partial')
    </div>
  @endif

  @if ($selectedService && $selectedSlot)
    @php
      $cancelSelectionUrl = $isReschedule
        ? $queryUrl(['date' => null, 'slot_start' => null], 'booking-step-service')
        : $queryUrl(['service_id' => null, 'date' => null, 'slot_start' => null], 'booking-step-service');
    @endphp
    <section id="booking-confirm" class="portal-panel booking-step booking-confirm-panel d-grid gap-3 p-4">
      <div class="section-heading d-flex align-items-center justify-content-between gap-3">
        <div>
          <h2>{{ $isReschedule ? 'Conferma spostamento' : 'Conferma prenotazione' }}</h2>
          <span>Controlla i dettagli prima di inviare</span>
        </div>
      </div>

      <dl class="booking-confirm-summary row g-3 mb-0">
        <div class="booking-confirm-summary__item col-12 col-md-6 h-100 p-3">
          <dt>Prestazione</dt>
          <dd class="mt-1 mb-0">{{ $selectedService->name }}</dd>
        </div>
        <div class="booking-confirm-summary__item col-12 col-md-6 h-100 p-3">
          <dt>Data e ora</dt>
          <dd class="mt-1 mb-0">{{ $selectedSlot->start_at->format('d/m/Y H:i') }} - {{ $selectedSlot->end_at->format('H:i') }}</dd>
        </div>
      </dl>

      <form method="POST" action="{{ $formAction }}" class="booking-confirm-form d-grid gap-3">
        @csrf
        @if (! $isReschedule)
          <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
          <label class="form-label">
            Note opzionali
            <textarea class="form-control" name="notes" rows="3" placeholder="Aggiungi indicazioni utili per il medico"></textarea>
          </label>
        @endif
        <input type="hidden" name="slot_start" value="{{ $selectedSlot->key }}">
        <button type="submit" class="btn btn-primary">
          {{ $isReschedule ? 'Conferma spostamento' : 'Conferma prenotazione' }}
        </button>
        <a class="btn btn-outline-secondary" href="{{ $cancelSelectionUrl }}">
          {{ $isReschedule ? 'Annulla spostamento' : 'Cambia selezione' }}
        </a>
      </form>
    </section>
  @endif
</div>
