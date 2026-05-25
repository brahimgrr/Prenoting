@php
  $queryUrl = function (array $params, ?string $fragment = null) use ($baseUrl): string {
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    $url = $params ? $baseUrl.'?'.http_build_query($params) : $baseUrl;

    return $fragment ? $url.'#'.$fragment : $url;
  };
@endphp

<div class="booking-wizard d-grid gap-3">
  <section id="booking-step-service" class="portal-panel booking-step d-grid gap-3 p-4">
    @if ($isReschedule)
      <div class="section-heading d-flex align-items-center justify-content-between gap-3">
        <h2>1. Prestazione selezionata</h2>
      </div>
      <div class="service-choice-card d-grid gap-2 h-100 p-3 border-primary bg-primary bg-opacity-10">
        <span class="service-choice-card__name">{{ $selectedService?->name ?? 'Appuntamento' }}</span>
        @if ($selectedService)
          <span class="service-choice-card__meta">
            {{ $selectedService->category }} · {{ $selectedService->duration_minutes }} min
          </span>
          @if ($selectedService->price)
            <span class="service-choice-card__price">€ {{ number_format((float) $selectedService->price, 2, ',', '.') }}</span>
          @endif
        @endif
      </div>
    @else
      <div class="section-heading d-flex align-items-center justify-content-between gap-3">
        <h2>1. Scegli la prestazione</h2>
        <span>{{ $services->count() }} disponibili</span>
      </div>
      <div class="service-card-grid row g-3">
        @foreach ($services as $service)
        @php
          $isSelected = $selectedService?->id === $service->id;
          $cardClasses = 'service-choice-card d-grid gap-2 h-100 p-3'.($isSelected ? ' border-primary bg-primary bg-opacity-10' : '');
          $href = $queryUrl([
            'service_id' => $service->id,
          ], 'booking-step-day');
        @endphp
        <div class="col-12 col-md-6">
          <a class="{{ $cardClasses }}" href="{{ $href }}">
            <span class="service-choice-card__name">{{ $service->name }}</span>
            <span class="service-choice-card__meta">
              {{ $service->category }} · {{ $service->duration_minutes }} min
            </span>
            @if ($service->price)
              <span class="service-choice-card__price">€ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
            @endif
          </a>
        </div>
        @endforeach
      </div>
    @endif
  </section>

  @if ($selectedService)
    <div id="booking-week-region" class="d-grid gap-3">
      @include('patient.partials.booking-week-partial')
    </div>
  @endif

  @if ($selectedService && $selectedSlot)
    @php
      $cancelSelectionUrl = $isReschedule
        ? '/patient/appointments'
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
        <div class="col-12 col-md-6">
          <div class="booking-confirm-summary__item h-100 p-3">
            <dt>Prestazione</dt>
            <dd class="mt-1 mb-0">{{ $selectedService->name }}</dd>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="booking-confirm-summary__item h-100 p-3">
            <dt>Data e ora</dt>
            <dd class="mt-1 mb-0">{{ $selectedSlot->start_at->format('d/m/Y H:i') }} - {{ $selectedSlot->end_at->format('H:i') }}</dd>
          </div>
        </div>
      </dl>

      <form method="POST" action="{{ $formAction }}" class="booking-confirm-form d-grid gap-3">
        @csrf
        @if (! $isReschedule)
          <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
          <label class="form-label d-grid gap-2 mb-0">
            Note opzionali
            <textarea class="form-control" name="notes" rows="3" placeholder="Aggiungi indicazioni utili per il medico"></textarea>
          </label>
        @endif
        <input type="hidden" name="slot_start" value="{{ $selectedSlot->key }}">
        <div class="booking-confirm-actions d-flex flex-column flex-sm-row gap-2">
          <button type="submit" class="btn btn-primary">
            {{ $isReschedule ? 'Conferma spostamento' : 'Conferma prenotazione' }}
          </button>
          <a class="btn btn-outline-secondary" href="{{ $cancelSelectionUrl }}">
            {{ $isReschedule ? 'Annulla spostamento' : 'Cambia selezione' }}
          </a>
        </div>
      </form>
    </section>
  @endif
</div>
