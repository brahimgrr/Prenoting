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

<article class="appointment-card {{ ($muted ?? false) ? 'appointment-card--muted' : '' }}">
  <div class="appointment-card__header">
    <div class="appointment-card__title">
      <h3>{{ $appointment->service?->name ?? 'Appuntamento' }}</h3>
    </div>
    @if ($muted ?? false)
      <span class="appointment-status-badge appointment-status-badge--{{ $historyStatusClass }}">{{ $historyStatusLabel }}</span>
    @endif
    @if ($manageable && ! $changeLocked)
      <div class="card-action-menu dropdown">
        <button
          class="btn btn-sm btn-outline-secondary card-action-menu__trigger"
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
    <p class="appointment-card__notice">Modifiche e cancellazioni non disponibili nelle 24 ore precedenti.</p>
  @endif
  <dl class="appointment-details">
    <div>
      <dt>Prestazione</dt>
      <dd>{{ $serviceCategoryLabel }}</dd>
    </div>
    <div>
      <dt>Orario</dt>
      <dd>{{ $appointment->start_at->format('d/m/Y H:i') }} - {{ $appointment->end_at->format('H:i') }}</dd>
    </div>
    <div>
      <dt>Prezzo visita</dt>
      <dd>{{ $priceLabel }}</dd>
    </div>
    <div>
      <dt>Note</dt>
      <dd>{{ filled($appointment->notes) ? $appointment->notes : 'Nessuna nota' }}</dd>
    </div>
    @if ($appointment->status === \App\Models\Appointment::STATUS_CANCELLED)
      <div>
        <dt>Annullamento</dt>
        <dd>{{ $appointment->cancellationActorLabel() }}</dd>
      </div>
      <div>
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
