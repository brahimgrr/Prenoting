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
      <form method="POST" action="/appointments/{{ $appointment->id }}/reschedule" class="reschedule-form" data-service="{{ $appointment->service_id }}">
        @csrf
        <input type="date" class="form-control reschedule-date" value="{{ now()->toDateString() }}">
        <select class="form-select reschedule-slot-select" name="slot_id" required>
          <option value="">Scegli nuovo orario</option>
        </select>
        <button type="submit" class="btn btn-outline-secondary">Sposta</button>
      </form>
      <form method="POST" action="/appointments/{{ $appointment->id }}/cancel" class="appointment-cancel-form">
        @csrf
        <input type="text" class="form-control" name="cancellation_reason" placeholder="Motivo opzionale">
        <button type="submit" class="btn btn-outline-danger">Annulla</button>
      </form>
    </div>
  @else
    <div class="appointment-actions appointment-actions--readonly">
      <span class="text-secondary fw-medium">Solo dettagli</span>
    </div>
  @endif
</article>
