@extends('layouts.portal', ['title' => 'Trattamenti - MedPortal'])

@php
  $editing = $editingOffering ?? null;
  $categoryLabels = [
    \App\Models\MedicalService::CATEGORY_VISIT => 'Visita',
    \App\Models\MedicalService::CATEGORY_EXAM => 'Esame',
  ];
@endphp

@section('content')
  <section class="portal-section operations-dashboard">
    <div class="portal-page-heading portal-heading-row">
      <div>
        <span class="portal-eyebrow">Portale medico</span>
        <h1>Trattamenti</h1>
        <p>Gestisci le prestazioni private del tuo studio senza modificare il catalogo globale usato dai pazienti.</p>
      </div>
      <a href="/doctor/schedule" class="btn btn-outline-secondary">Torna all'agenda</a>
    </div>

    <section class="portal-panel dashboard-filter-panel">
      <div class="section-heading">
        <h2>{{ $editing ? 'Modifica trattamento' : 'Aggiungi trattamento' }}</h2>
        <span>{{ $editing ? 'Aggiorna durata, prezzo e visibilita' : 'Crea una nuova offerta' }}</span>
      </div>
      <form class="treatment-form-grid" method="POST" action="{{ $editing ? "/doctor/treatments/{$editing->id}" : '/doctor/treatments' }}">
        @csrf
        @if ($editing)
          @method('PATCH')
        @endif
        <label class="form-label">
          Nome
          <input class="form-control" name="name" value="{{ old('name', $editing?->name) }}" placeholder="Es. Visita cardiologica di controllo" required>
        </label>
        <label class="form-label">
          Categoria
          <select class="form-select" name="category" required>
            @foreach ($categoryLabels as $categoryValue => $categoryLabel)
              <option value="{{ $categoryValue }}" @selected(old('category', $editing?->category ?? \App\Models\MedicalService::CATEGORY_VISIT) === $categoryValue)>
                {{ $categoryLabel }}
              </option>
            @endforeach
          </select>
        </label>
        <label class="form-label">
          Specialita
          <select class="form-select" name="specialty_id" required>
            @foreach ($specialties as $specialty)
              <option value="{{ $specialty->id }}" @selected((int) old('specialty_id', $editing?->specialty_id) === $specialty->id)>
                {{ $specialty->name }}
              </option>
            @endforeach
          </select>
        </label>
        <label class="form-label">
          Durata
          <select class="form-select" name="duration_minutes" required>
            @foreach ([15, 20, 30, 45, 60, 90, 120] as $duration)
              <option value="{{ $duration }}" @selected((int) old('duration_minutes', $editing?->duration_minutes ?? 30) === $duration)>{{ $duration }} min</option>
            @endforeach
          </select>
        </label>
        <label class="form-label">
          Prezzo
          <input class="form-control" type="number" name="price" step="0.01" min="0" value="{{ old('price', $editing?->price) }}" placeholder="Es. 90.00">
        </label>
        <label class="form-check treatment-active-check">
          <input type="hidden" name="is_active" value="0">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editing?->is_active ?? true))>
          <span class="form-check-label">Trattamento attivo</span>
        </label>
        <div class="treatment-form-actions">
          <button type="submit" class="btn btn-primary">{{ $editing ? 'Salva modifiche' : 'Aggiungi trattamento' }}</button>
          @if ($editing)
            <a href="/doctor/treatments" class="btn btn-outline-secondary">Annulla modifica</a>
          @endif
        </div>
      </form>
    </section>

    <section class="portal-panel dashboard-table-panel">
      <div class="section-heading">
        <h2>I tuoi trattamenti</h2>
        <span>{{ $offerings->count() }} offerte</span>
      </div>

      @if ($offerings->isNotEmpty())
        <div class="treatment-card-grid">
          @foreach ($offerings as $offering)
            <article class="treatment-card">
              <div class="treatment-card__header">
                <div>
                  <h3>{{ $offering->name }}</h3>
                  <span>{{ $categoryLabels[$offering->category] ?? $offering->category }} · {{ $offering->specialty?->name }}</span>
                </div>
                <span class="badge {{ $offering->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                  {{ $offering->is_active ? 'Attivo' : 'Inattivo' }}
                </span>
              </div>
              <dl class="treatment-card__facts">
                <div>
                  <dt>Durata</dt>
                  <dd>{{ $offering->duration_minutes }} min</dd>
                </div>
                <div>
                  <dt>Prezzo</dt>
                  <dd>{{ $offering->price !== null ? 'EUR '.number_format((float) $offering->price, 2, ',', '.') : 'Da definire' }}</dd>
                </div>
              </dl>
              <div class="treatment-card__actions">
                <a href="/doctor/treatments/{{ $offering->id }}/edit" class="btn btn-sm btn-outline-primary">Modifica</a>
                <form method="POST" action="/doctor/treatments/{{ $offering->id }}/status">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="is_active" value="{{ $offering->is_active ? '0' : '1' }}">
                  <button type="submit" class="btn btn-sm {{ $offering->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    {{ $offering->is_active ? 'Disattiva' : 'Riattiva' }}
                  </button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessun trattamento configurato</h3>
          <p>Aggiungi la prima prestazione per costruire il tuo catalogo privato.</p>
        </div>
      @endif
    </section>
  </section>
@endsection
