@php
  $cancelModalId = "appointmentCancelModal{$appointment->id}";
  $serviceCategoryLabel = match ($appointment->service?->category) {
    'ESAME' => 'ESAME',
    default => 'VISITA',
  };
  $priceLabel = $appointment->service?->price !== null
    ? 'EUR ' . number_format((float) $appointment->service->price, 2, ',', '.')
    : 'Da definire';
  $historyStatusLabel = $appointment->status === \App\Models\Appointment::STATUS_CANCELLED ? 'Annullato' : 'Passato';
  $historyStatusClass = $appointment->status === \App\Models\Appointment::STATUS_CANCELLED ? 'cancelled' : 'past';
  $changeLocked = ($manageable ?? false) && $appointment->start_at->lte(now()->addDay());
@endphp

<article class="appointment-card d-grid gap-3 p-3 mb-3 {{ ($muted ?? false) ? 'appointment-card--muted' : '' }}">
  <div class="appointment-card__header d-flex align-items-center justify-content-between gap-3">
    <div class="appointment-card__title">
      <h3>{{ $appointment->service?->name ?? 'Appuntamento' }}</h3>
    </div>
    @if ($muted ?? false)
      <span class="appointment-status-badge appointment-status-badge--{{ $historyStatusClass }} d-inline-flex align-items-center flex-shrink-0 px-2 py-1">{{ $historyStatusLabel }}</span>
    @endif
    @if ($manageable && ! $changeLocked)
      <div class="card-action-menu dropdown ms-auto flex-shrink-0">
        <button
          class="btn btn-sm btn-outline-secondary card-action-menu__trigger d-inline-flex align-items-center justify-content-center px-2"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          aria-label="Azioni appuntamento"
        >...</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item" href="/appointments/{{ $appointment->id }}/edit">Sposta appuntamento</a>
          </li>
          <li>
            <button
              class="dropdown-item text-danger"
              type="button"
              data-bs-toggle="modal"
              data-bs-target="#{{ $cancelModalId }}"
            >Annulla appuntamento</button>
          </li>
        </ul>
      </div>
    @endif
  </div>
  @if ($manageable && $changeLocked)
    <p class="appointment-card__notice m-0 px-3 py-2">Modifiche e cancellazioni non disponibili nelle 24 ore precedenti.</p>
  @endif
  <dl class="appointment-details row g-3 mb-0">
    <div class="col-12 col-md-6">
      <dt>Prestazione</dt>
      <dd>{{ $serviceCategoryLabel }}</dd>
    </div>
    <div class="col-12 col-md-6">
      <dt>Orario</dt>
      <dd>{{ $appointment->start_at->format('d/m/Y H:i') }} - {{ $appointment->end_at->format('H:i') }}</dd>
    </div>
    <div class="col-12 col-md-6">
      <dt>Prezzo visita</dt>
      <dd>{{ $priceLabel }}</dd>
    </div>
    <div class="col-12 col-md-6">
      <dt>Note</dt>
      <dd>{{ filled($appointment->notes) ? $appointment->notes : 'Nessuna nota' }}</dd>
    </div>
    @if ($appointment->status === \App\Models\Appointment::STATUS_CANCELLED)
      <div class="col-12 col-md-6">
        <dt>Annullamento</dt>
        <dd>{{ $appointment->cancellationActorLabel() }}</dd>
      </div>
      <div class="col-12 col-md-6">
        <dt>Motivo annullamento</dt>
        <dd>{{ filled($appointment->cancellation_reason) ? $appointment->cancellation_reason : 'Non indicato' }}</dd>
      </div>
    @endif
  </dl>
  @if ($manageable && ! $changeLocked)
    <x-appointment-cancel-modal
      :appointment="$appointment"
      :action="'/appointments/'.$appointment->id.'/cancel'"
      :modal-id="$cancelModalId"
    />
  @endif
</article>
