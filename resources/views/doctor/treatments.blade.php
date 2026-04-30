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
        <p>Gestisci le prestazioni prenotabili dai pazienti.</p>
      </div>
      <a href="/doctor/schedule" class="btn btn-outline-secondary">Torna all'agenda</a>
    </div>

    <section class="portal-panel dashboard-filter-panel">
      <div class="section-heading">
        <h2>{{ $editing ? 'Modifica trattamento' : 'Aggiungi trattamento' }}</h2>
        <span>{{ $editing ? 'Aggiorna nome, categoria e prezzo' : 'Crea una nuova prestazione' }}</span>
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
          Prezzo
          <input class="form-control" type="number" name="price" step="0.01" min="0" value="{{ old('price', $editing?->price) }}" placeholder="Es. 90.00">
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
        <span>{{ $offerings->count() }} prestazioni</span>
      </div>

      @if ($offerings->isNotEmpty())
        <div class="treatment-card-grid">
          @foreach ($offerings as $offering)
            <article class="treatment-card">
              <div class="treatment-card__header">
                <div>
                  <h3>{{ $offering->name }}</h3>
                  <span>{{ $categoryLabels[$offering->category] ?? $offering->category }}</span>
                </div>
              </div>
              <dl class="treatment-card__facts">
                <div>
                  <dt>Prezzo</dt>
                  <dd>{{ $offering->price !== null ? 'EUR '.number_format((float) $offering->price, 2, ',', '.') : 'Da definire' }}</dd>
                </div>
              </dl>
              <div class="treatment-card__actions">
                <a href="/doctor/treatments/{{ $offering->id }}/edit" class="btn btn-sm btn-outline-primary">Modifica</a>
                <form method="POST" action="/doctor/treatments/{{ $offering->id }}" onsubmit="return window.confirm('Eliminare questo trattamento?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger">Elimina</button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="empty-state">
          <h3>Nessun trattamento configurato</h3>
          <p>Aggiungi la prima prestazione prenotabile dai pazienti.</p>
        </div>
      @endif
    </section>
  </section>
@endsection
