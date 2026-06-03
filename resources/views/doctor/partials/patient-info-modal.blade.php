@php
  $patient = $appointment->patient;
  $user = $patient?->user;
@endphp

<div
  class="modal fade appointment-info-modal"
  id="{{ $modalId }}"
  tabindex="-1"
>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable appointment-info-modal__dialog">
    <div class="modal-content">
      <div class="modal-header appointment-info-modal__header">
        <div>
          <span class="appointment-info-modal__eyebrow">Paziente</span>
          <h2 class="modal-title h5 mb-0" id="{{ $modalId }}Label">
            Informazioni paziente
          </h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body appointment-info-modal__body">
        <section class="appointment-info-card">
          <div class="appointment-info-card__header">
            <h3 id="{{ $modalId }}Patient">Dati paziente</h3>
          </div>
          <dl class="appointment-info-card__list">
            <div>
              <dt>Nome</dt>
              <dd>{{ $appointment->patientName() }}</dd>
            </div>
            <div>
              <dt>Telefono</dt>
              <dd>{{ $patient?->phone ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{{ $user?->email ?: 'Non indicata' }}</dd>
            </div>
            <div>
              <dt>Data di nascita</dt>
              <dd>{{ $patient?->date_of_birth?->format('d/m/Y') ?: 'Non indicata' }}</dd>
            </div>
            <div>
              <dt>Luogo di nascita</dt>
              <dd>{{ $patient?->place_of_birth ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Genere</dt>
              <dd>{{ $patient?->gender ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Indirizzo</dt>
              <dd>{{ $patient?->address ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Codice fiscale</dt>
              <dd>{{ $patient?->codice_fiscale ?: 'Non indicato' }}</dd>
            </div>
          </dl>
        </section>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>
