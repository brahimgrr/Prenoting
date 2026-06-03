@php
  $doctor = $appointment->doctor;
  $user = $doctor?->user;
  $doctorName = $doctor?->display_name ?: ($user?->displayName() ?? 'Medico');
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
          <span class="appointment-info-modal__eyebrow">Medico</span>
          <h2 class="modal-title h5 mb-0" id="{{ $modalId }}Label">
            Informazioni medico
          </h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body appointment-info-modal__body">
        <section class="appointment-info-card">
          <div class="appointment-info-card__header">
            <h3 id="{{ $modalId }}Doctor">Dati medico</h3>
          </div>
          <dl class="appointment-info-card__list">
            <div>
              <dt>Nome</dt>
              <dd>{{ $doctorName }}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{{ $user?->email ?: 'Non indicata' }}</dd>
            </div>
            <div>
              <dt>Telefono</dt>
              <dd>{{ $doctor?->phone ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Indirizzo studio</dt>
              <dd>{{ $doctor?->clinic_address ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Numero albo</dt>
              <dd>{{ $doctor?->license_number ?: 'Non indicato' }}</dd>
            </div>
            <div>
              <dt>Bio</dt>
              <dd>{{ $doctor?->bio ?: 'Non indicata' }}</dd>
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
