@props([
  'appointment',
  'action',
  'modalId' => null,
])

@php
  $modalId = $modalId ?? "appointmentCancelModal{$appointment->id}";
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="{{ $action }}">
        @csrf
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="{{ $modalId }}Label">Conferma annullamento</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
