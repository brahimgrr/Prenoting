@php
  $cancelPanelId = "appointmentCancelPanel{$appointment->id}";
  $serviceCategoryLabel = match ($appointment->service?->category) {
    'ESAME' => 'ESAME',
    default => 'VISITA',
  };
@endphp

<article class="appointment-card {{ ($muted ?? false) ? 'appointment-card--muted' : '' }}">
  <div class="appointment-card__header">
    <div class="appointment-card__title">
      <h3>{{ $appointment->service?->name ?? 'Appuntamento' }}</h3>
    </div>
    @if ($manageable)
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
              data-cancel-panel-target="#{{ $cancelPanelId }}"
            >Annulla appuntamento</button>
          </li>
        </ul>
      </div>
    @endif
  </div>
  <dl class="appointment-details">
    <div>
      <dt>Prestazione</dt>
      <dd>{{ $serviceCategoryLabel }}</dd>
    </div>
    <div>
      <dt>Orario</dt>
      <dd>{{ $appointment->start_at->format('d/m/Y H:i') }} - {{ $appointment->end_at->format('H:i') }}</dd>
    </div>
  </dl>
  @if ($manageable)
    <div id="{{ $cancelPanelId }}" class="appointment-cancel-panel d-none" aria-hidden="true">
      <div class="appointment-cancel-panel__header">
        <div>
          <strong>Conferma annullamento</strong>
          <p>Lo slot verra liberato e tornera disponibile.</p>
        </div>
        <button type="button" class="btn-close" data-cancel-panel-close aria-label="Chiudi conferma annullamento"></button>
      </div>
      <form
        method="POST"
        action="/appointments/{{ $appointment->id }}/cancel"
        class="appointment-cancel-form"
        onsubmit="return window.confirm('Conferma annullamento: lo slot verra liberato.');"
      >
        @csrf
        <label class="form-label" for="cancellationReason{{ $appointment->id }}">Motivo opzionale</label>
        <input id="cancellationReason{{ $appointment->id }}" type="text" class="form-control" name="cancellation_reason" placeholder="Es. imprevisto personale">
        <div class="appointment-cancel-actions">
          <span class="text-danger fw-semibold">Conferma annullamento</span>
          <button type="submit" class="btn btn-sm btn-danger">Si, annulla</button>
        </div>
      </form>
    </div>
  @endif
</article>
