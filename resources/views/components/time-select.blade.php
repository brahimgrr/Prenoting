@props([
  'name',
  'value' => '',
  'includeEndOfDay' => false,
])

@php
  $selectedValue = substr((string) $value, 0, 5);
@endphp

<select
  class="{{ $attributes->get('class', 'form-control') }}"
  @if ($attributes->has('id')) id="{{ $attributes->get('id') }}" @endif
  name="{{ $name }}"
  {{ $attributes->except(['class', 'id']) }}
>
  @if ($selectedValue !== '')
    <option value="{{ $selectedValue }}" selected>{{ $selectedValue }}</option>
    <option value="">--:--</option>
  @else
    <option value="">--:--</option>
  @endif
  @for ($hour = 0; $hour < 24; $hour++)
    @foreach ([0, 30] as $minute)
      @php $optionValue = sprintf('%02d:%02d', $hour, $minute); @endphp
      @if ($selectedValue !== $optionValue)
        <option value="{{ $optionValue }}">{{ $optionValue }}</option>
      @endif
    @endforeach
  @endfor
  @if ($includeEndOfDay && $selectedValue !== '24:00')
    <option value="24:00">24:00</option>
  @endif
</select>
