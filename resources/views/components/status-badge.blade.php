@php
  $normalized = strtolower((string) $status);
  $variants = [
    'confirmed' => 'text-bg-success',
    'checked_in' => 'text-bg-info',
    'completed' => 'text-bg-primary',
    'cancelled' => 'text-bg-danger',
    'no_show' => 'text-bg-danger',
    'scheduled' => 'text-bg-info',
  ];
  $labels = [
    'confirmed' => 'Confermato',
    'checked_in' => 'Accettato',
    'completed' => 'Completato',
    'cancelled' => 'Annullato',
    'no_show' => 'Assente',
    'scheduled' => 'Programmato',
  ];
@endphp
<span class="badge rounded-pill {{ $variants[$normalized] ?? 'text-bg-secondary' }}">
  {{ $slot ?? ($labels[$normalized] ?? str_replace('_', ' ', (string) $status)) }}
</span>
