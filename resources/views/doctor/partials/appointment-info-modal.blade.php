@php
  $serviceCategoryLabel = match ($appointment->service?->category) {
    'VISITA' => 'Visita',
    'ESAME' => 'Esame',
    default => '—',
  };
@endphp

<div
  class="modal fade appointment-info-modal"
  id="appointmentInfoModal{{ $appointment->id }}"
  tabindex="-1"
  aria-labelledby="appointmentInfoModal{{ $appointment->id }}Label"
  aria-hidden="true"
>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable appointment-info-modal__dialog">
    <div class="modal-content">
      <div class="modal-header appointment-info-modal__header">
        <div>
          <span class="appointment-info-modal__eyebrow">Agenda</span>
          <h2 class="modal-title h5 mb-0" id="appointmentInfoModal{{ $appointment->id }}Label">
            Informazioni appuntamento
          </h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body appointment-info-modal__body">
        <section class="appointment-info-summary" aria-label="Riepilogo appuntamento">
          <div>
            <span>Prestazione</span>
            <strong>{{ $appointment->service?->name ?? 'Appuntamento' }}</strong>
          </div>
          <div>
            <span>Quando</span>
            <strong>{{ $appointment->start_at->format('d/m/Y H:i') }} - {{ $appointment->end_at->format('H:i') }}</strong>
          </div>
        </section>

        <div class="appointment-info-grid">
          <section class="appointment-info-card" aria-labelledby="appointmentInfoPatient{{ $appointment->id }}">
            <div class="appointment-info-card__header">
              <h3 id="appointmentInfoPatient{{ $appointment->id }}">Paziente</h3>
            </div>
            <dl class="appointment-info-card__list">
              <div>
                <dt>Nome</dt>
                <dd>{{ $appointment->patientName() }}</dd>
              </div>
              <div>
                <dt>Telefono</dt>
                <dd>{{ $appointment->patient?->phone ?? '—' }}</dd>
              </div>
              <div>
                <dt>Email</dt>
                <dd>{{ $appointment->patient?->user?->email ?? '—' }}</dd>
              </div>
              <div>
                <dt>Data di nascita</dt>
                <dd>{{ $appointment->patient?->date_of_birth?->format('d/m/Y') ?? '—' }}</dd>
              </div>
              <div>
                <dt>Codice fiscale</dt>
                <dd>{{ $appointment->patient?->codice_fiscale ?? '—' }}</dd>
              </div>
            </dl>
          </section>

          <section class="appointment-info-card mt-3" aria-labelledby="appointmentInfoService{{ $appointment->id }}">
            <div class="appointment-info-card__header">
              <h3 id="appointmentInfoService{{ $appointment->id }}">Prestazione</h3>
            </div>
            <dl class="appointment-info-card__list">
              <div>
                <dt>Nome</dt>
                <dd>{{ $appointment->service?->name ?? '—' }}</dd>
              </div>
              <div>
                <dt>Categoria</dt>
                <dd>{{ $serviceCategoryLabel }}</dd>
              </div>
              <div>
                <dt>Durata</dt>
                <dd>{{ $appointment->service?->duration_minutes ? $appointment->service->duration_minutes . ' min' : '—' }}</dd>
              </div>
              <div>
                <dt>Prezzo</dt>
                <dd>{{ $appointment->service?->price !== null ? 'EUR ' . number_format((float) $appointment->service->price, 2, ',', '.') : 'Da definire' }}</dd>
              </div>
            </dl>
          </section>
        </div>

        <section class="appointment-info-card appointment-info-card--notes" aria-labelledby="appointmentInfoNotes{{ $appointment->id }}">
          <div class="appointment-info-card__header">
            <h3 id="appointmentInfoNotes{{ $appointment->id }}">Note di prenotazione</h3>
          </div>
          <p class="appointment-info-card__notes">{{ $appointment->notes ?? 'Nessuna nota' }}</p>
        </section>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>
