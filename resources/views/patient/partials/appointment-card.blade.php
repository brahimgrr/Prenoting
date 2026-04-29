<article class="appointment-card {{ ($muted ?? false) ? 'appointment-card--muted' : '' }}">
  <div class="appointment-card__header">
    <div>
      <h3>{{ $appointment->service?->name ?? 'Appuntamento' }}</h3>
      <p>{{ $appointment->start_at->format('d/m/Y H:i') }}</p>
    </div>
    <x-status-badge :status="$appointment->status" />
  </div>
  <dl class="appointment-details">
    <div>
      <dt>Prestazione</dt>
      <dd>{{ $appointment->service?->name ?? 'Appuntamento' }}</dd>
    </div>
    <div>
      <dt>Medico</dt>
      <dd>{{ $appointment->doctor?->display_name ?? 'Medico in attesa' }}</dd>
    </div>
    <div>
      <dt>Ambulatorio</dt>
      <dd>{{ $appointment->clinic?->name ?? 'Ambulatorio in attesa' }}</dd>
    </div>
    <div>
      <dt>Orario</dt>
      <dd>{{ $appointment->start_at->format('d/m/Y H:i') }}</dd>
    </div>
  </dl>
  @if ($manageable)
    <div class="appointment-actions">
      <a class="btn btn-sm btn-outline-primary" href="/appointments/{{ $appointment->id }}/edit">Modifica</a>
      <details class="appointment-cancel-details">
        <summary class="btn btn-sm btn-outline-danger">Elimina appuntamento</summary>
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
      </details>
    </div>
  @else
    <div class="appointment-actions appointment-actions--readonly">
      <span class="text-secondary fw-medium">Solo dettagli</span>
    </div>
  @endif
</article>
