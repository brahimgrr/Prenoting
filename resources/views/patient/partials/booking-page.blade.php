<section class="portal-section booking-wizard-page container-xl">
  <div class="portal-page-heading mb-4">
    <span class="portal-eyebrow">{{ $eyebrow }}</span>
    <h1>{{ $heading }}</h1>
    @isset($appointment)
      <p>
        Stai riprogrammando:
        <strong>{{ $appointment->service?->name ?? 'Appuntamento' }}</strong>
        del {{ $appointment->start_at->format('d/m/Y H:i') }}.
      </p>
    @endisset
  </div>

  @include('patient.partials.booking-wizard')
  @include('patient.partials.booking-interactions')
</section>
