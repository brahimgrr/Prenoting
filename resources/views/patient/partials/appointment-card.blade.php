@php
  $cancelModalId = "appointmentCancelModal{$appointment->id}";
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
              data-bs-toggle="modal"
              data-bs-target="#{{ $cancelModalId }}"
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
    <div class="modal fade" id="{{ $cancelModalId }}" tabindex="-1" aria-labelledby="{{ $cancelModalId }}Label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="/appointments/{{ $appointment->id }}/cancel">
            @csrf
            <div class="modal-header">
              <h2 class="modal-title fs-5" id="{{ $cancelModalId }}Label">Conferma annullamento</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi conferma annullamento"></button>
            </div>
            <div class="modal-body">
              <p>Lo slot verra liberato e tornera disponibile.</p>
              <label class="form-label" for="cancellationReason{{ $appointment->id }}">Motivo opzionale</label>
              <input id="cancellationReason{{ $appointment->id }}" type="text" class="form-control" name="cancellation_reason" placeholder="Es. imprevisto personale">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
              <button type="submit" class="btn btn-danger">Si, annulla appuntamento</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
</article>
