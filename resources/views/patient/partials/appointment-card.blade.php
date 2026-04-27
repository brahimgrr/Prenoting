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
      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelAppointmentModal{{ $appointment->id }}">
        Annulla
      </button>
    </div>
    <div class="modal fade" id="cancelAppointmentModal{{ $appointment->id }}" tabindex="-1" aria-labelledby="cancelAppointmentModal{{ $appointment->id }}Label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="cancelAppointmentModal{{ $appointment->id }}Label">Annulla appuntamento</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
          </div>
          <form method="POST" action="/appointments/{{ $appointment->id }}/cancel">
            @csrf
            <div class="modal-body">
              <p class="mb-2">
                Stai annullando <strong>{{ $appointment->service?->name ?? 'Appuntamento' }}</strong>
                del {{ $appointment->start_at->format('d/m/Y H:i') }}.
              </p>
              <p class="text-danger fw-semibold">Questa azione libera lo slot e non puo essere annullata automaticamente.</p>
              <label class="form-label" for="cancellationReason{{ $appointment->id }}">Motivo opzionale</label>
              <input id="cancellationReason{{ $appointment->id }}" type="text" class="form-control" name="cancellation_reason" placeholder="Es. imprevisto personale">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Torna indietro</button>
              <button type="submit" class="btn btn-danger">Si, annulla</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @else
    <div class="appointment-actions appointment-actions--readonly">
      <span class="text-secondary fw-medium">Solo dettagli</span>
    </div>
  @endif
</article>
