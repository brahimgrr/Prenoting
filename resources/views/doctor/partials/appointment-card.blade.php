@php
  $cancelModalId = "appointmentCancelModal{$appointment->id}";
  $patientInfoModalId = "patientInfoModal{$appointment->id}";
  $serviceCategoryLabel = match ($appointment->service?->category) {
    'ESAME' => 'ESAME',
    default => 'VISITA',
  };
  $priceLabel = $appointment->service?->price !== null
    ? 'EUR ' . number_format((float) $appointment->service->price, 2, ',', '.')
    : 'Da definire';
  $historyStatusLabel = match (true) {
    $appointment->status === \App\Models\Appointment::STATUS_CANCELLED && $appointment->cancelled_by_role === \App\Models\Appointment::CANCELLED_BY_PATIENT => 'Annullato dal paziente',
    $appointment->status === \App\Models\Appointment::STATUS_CANCELLED && $appointment->cancelled_by_role === \App\Models\Appointment::CANCELLED_BY_DOCTOR => 'Annullato da te',
    $appointment->status === \App\Models\Appointment::STATUS_CANCELLED => 'Annullato',
    default => 'Passato',
  };
  $historyStatusClass = $appointment->status === \App\Models\Appointment::STATUS_CANCELLED ? 'cancelled' : 'past';
  $hasBookingNotes = filled($appointment->notes);
@endphp

<article class="appointment-card d-grid gap-3 p-3 mb-3 {{ ($muted ?? false) ? 'appointment-card--muted' : '' }}">
  <div class="appointment-card__header d-flex align-items-center justify-content-between gap-3">
    <div class="appointment-card__title">
      <h3 class="mb-0">{{ $appointment->patientName() }}</h3>
    </div>
    @if ($muted ?? false)
      <span class="appointment-status-badge appointment-status-badge--{{ $historyStatusClass }} d-inline-flex align-items-center flex-shrink-0 px-2 py-1">{{ $historyStatusLabel }}</span>
    @endif
    <div class="appointment-card__actions d-inline-flex align-items-center gap-2 ms-auto flex-shrink-0">
      <button
        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center px-2"
        type="button"
        title="Informazioni paziente"
        aria-label="Informazioni paziente"
        data-bs-toggle="modal"
        data-bs-target="#{{ $patientInfoModalId }}"
      ><i class="bi bi-info-circle" aria-hidden="true"></i></button>
      @if ($manageable)
        <button
          class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center px-2"
          type="button"
          title="Annulla appuntamento"
          aria-label="Annulla appuntamento"
          data-bs-toggle="modal"
          data-bs-target="#{{ $cancelModalId }}"
        ><i class="bi bi-trash" aria-hidden="true"></i></button>
      @endif
    </div>
  </div>
  <dl class="appointment-details row g-3 mb-0">
    <div class="col-12 col-md-6">
      <dt>Prestazione</dt>
      <dd>{{ $appointment->service?->name ?? 'Appuntamento' }} · {{ $serviceCategoryLabel }}</dd>
    </div>
    <div class="col-12 col-md-6">
      <dt>Orario</dt>
      <dd>{{ $appointment->start_at->format('d/m/Y H:i') }} - {{ $appointment->end_at->format('H:i') }}</dd>
    </div>
    <div class="col-12 col-md-6">
      <dt>Prezzo visita</dt>
      <dd>{{ $priceLabel }}</dd>
    </div>
    @if ($hasBookingNotes)
      <div class="col-12 col-md-6">
        <dt>Note</dt>
        <dd>{{ $appointment->notes }}</dd>
      </div>
    @endif
    @if ($appointment->status === \App\Models\Appointment::STATUS_CANCELLED)
      <div class="col-12 col-md-6">
        <dt>Motivo annullamento</dt>
        <dd>{{ filled($appointment->cancellation_reason) ? $appointment->cancellation_reason : 'Non indicato' }}</dd>
      </div>
    @endif
  </dl>
  @if ($manageable)
    <x-appointment-cancel-modal
      :appointment="$appointment"
      :action="'/doctor/appointments/'.$appointment->id.'/cancel'"
      :modal-id="$cancelModalId"
    />
  @endif
  @include('doctor.partials.patient-info-modal', ['appointment' => $appointment, 'modalId' => $patientInfoModalId])
</article>
