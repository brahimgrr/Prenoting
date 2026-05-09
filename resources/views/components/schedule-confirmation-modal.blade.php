@php
  $confirmation = $confirmation ?? null;
  $method = strtoupper((string) ((is_array($confirmation) ? ($confirmation['method'] ?? null) : null) ?? 'POST'));
  $hiddenInputs = function (array $values, ?string $prefix = null) use (&$hiddenInputs): string {
    $html = '';

    foreach ($values as $key => $value) {
      $name = $prefix === null ? (string) $key : "{$prefix}[{$key}]";

      if (is_array($value)) {
        $html .= $hiddenInputs($value, $name);
        continue;
      }

      if ($value === null) {
        continue;
      }

      $html .= '<input type="hidden" name="'.e($name).'" value="'.e((string) $value).'">';
    }

    return $html;
  };
@endphp

@if ($confirmation)
  <div class="modal fade" id="scheduleConfirmationModal" tabindex="-1" aria-labelledby="scheduleConfirmationModalLabel" aria-hidden="true" data-auto-show-modal>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="{{ $confirmation['action'] }}">
          @csrf
          @if ($method !== 'POST')
            <input type="hidden" name="_method" value="{{ $method }}">
          @endif
          <input type="hidden" name="{{ \App\Services\DoctorScheduleService::CONFIRM_APPOINTMENT_CANCELLATIONS_FIELD }}" value="1">
          {!! $hiddenInputs($confirmation['payload'] ?? []) !!}

          <div class="modal-header">
            <h2 class="modal-title fs-5" id="scheduleConfirmationModalLabel">{{ $confirmation['title'] ?? 'Conferma modifica agenda' }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
          </div>
          <div class="modal-body">
            <p>{{ $confirmation['message'] ?? 'Questi appuntamenti verranno annullati per completare la modifica.' }}</p>
            <div class="list-group mb-3">
              @foreach (($confirmation['appointments'] ?? []) as $appointment)
                <div class="list-group-item">
                  <div class="fw-semibold">{{ $appointment['starts_at'] }} - {{ $appointment['patient_name'] }}</div>
                  <div class="small text-muted">{{ $appointment['service_name'] }} fino alle {{ $appointment['ends_at'] }}</div>
                </div>
              @endforeach
            </div>
            <p class="mb-0 small text-muted">
              Motivo annullamento: <strong>{{ $confirmation['reason'] }}</strong>
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="submit" class="btn btn-danger">Conferma e annulla appuntamenti</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
