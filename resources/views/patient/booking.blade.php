@extends('layouts.portal', ['title' => 'Prenota visita - MedPortal'])

@section('content')
  <section class="portal-section booking-page" data-initial-mode="{{ $mode }}">
    <div class="portal-page-heading">
      <span class="portal-eyebrow">Prenotazione</span>
      <h1>Prenota visita</h1>
    </div>

    <div class="booking-layout">
      <section class="portal-panel booking-search-panel">
        <ul class="nav nav-tabs portal-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link booking-mode-tab {{ $mode === 'service' ? 'active' : '' }}" data-mode="service">Prestazione</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link booking-mode-tab {{ $mode === 'doctor' ? 'active' : '' }}" data-mode="doctor">Medico</button>
          </li>
        </ul>

        <div class="booking-filters">
          <label class="form-label" for="booking-search">Cerca</label>
          <input id="booking-search" class="form-control" placeholder="Cerca prestazioni o medici">
          <label class="form-label" for="clinic-filter">ID ambulatorio</label>
          <input id="clinic-filter" class="form-control" inputmode="numeric" pattern="[0-9]*" placeholder="Opzionale">
          <label class="form-label" for="booking-date">Data</label>
          <input id="booking-date" class="form-control" type="date" value="{{ now()->toDateString() }}">
        </div>

        <div class="result-list result-list--services {{ $mode === 'service' ? '' : 'd-none' }}">
          @foreach ($services as $service)
            <button type="button" class="result-item booking-service" data-id="{{ $service->id }}" data-name="{{ $service->name }}" data-specialty="{{ $service->specialty_id }}">
              <span>
                <strong>{{ $service->name }}</strong>
                <small>{{ $service->specialty?->name ?? $service->category }}</small>
              </span>
              <span>{{ $service->duration_minutes }} min</span>
            </button>
          @endforeach
        </div>

        <div class="result-list result-list--doctors {{ $mode === 'doctor' ? '' : 'd-none' }}">
          @foreach ($doctors as $doctor)
            <button type="button" class="result-item booking-doctor" data-id="{{ $doctor->id }}" data-name="{{ $doctor->display_name }}" data-services="{{ $doctor->services->pluck('id')->join(',') }}">
              <span>
                <strong>{{ $doctor->display_name }}</strong>
                <small>{{ $doctor->specialty?->name ?? 'Medico' }}</small>
              </span>
              <span>#{{ $doctor->id }}</span>
            </button>
          @endforeach
        </div>
      </section>

      <section class="portal-panel">
        <div class="section-heading">
          <h2>Orari disponibili</h2>
        </div>
        <div class="doctor-service-wrap mb-3 {{ $mode === 'doctor' ? '' : 'd-none' }}">
          <label class="form-label" for="doctor-service">Prestazione</label>
          <select id="doctor-service" class="form-select">
            <option value="">Scegli prestazione</option>
            @foreach ($services as $service)
              <option value="{{ $service->id }}">{{ $service->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="empty-state booking-empty">
          <h3>Seleziona una prestazione o un medico</h3>
          <p>Gli orari disponibili compariranno dopo la selezione.</p>
        </div>
        <div class="slot-list booking-slots"></div>
      </section>

      <section class="portal-panel confirmation-panel">
        <div class="section-heading">
          <h2>Conferma</h2>
        </div>
        <form method="POST" action="/appointments" id="booking-form">
          @csrf
          <input type="hidden" name="service_id" id="booking-service-id">
          <input type="hidden" name="slot_id" id="booking-slot-id">
          <dl class="summary-list">
            <div>
              <dt>Prestazione</dt>
              <dd id="summary-service">Non selezionata</dd>
            </div>
            <div>
              <dt>Orario</dt>
              <dd id="summary-slot">Non selezionato</dd>
            </div>
            <div>
              <dt>Medico</dt>
              <dd id="summary-doctor">Non selezionato</dd>
            </div>
            <div>
              <dt>Ambulatorio</dt>
              <dd id="summary-clinic">Non selezionato</dd>
            </div>
          </dl>
          <label class="form-label" for="appointment-notes">Note</label>
          <textarea id="appointment-notes" class="form-control" name="notes" rows="4"></textarea>
          <button type="submit" class="btn btn-primary w-100 mt-3" id="booking-submit" disabled>Conferma</button>
        </form>
      </section>
    </div>
  </section>
@endsection
