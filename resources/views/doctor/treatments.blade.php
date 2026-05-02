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
      </div>
    </div>

    <section class="portal-panel dashboard-filter-panel">
      <div class="section-heading">
        <h2>{{ $editing ? 'Modifica trattamento' : 'Aggiungi trattamento' }}</h2>
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

    <section class="portal-panel dashboard-table-panel treatments-list-panel">
      <div class="section-heading">
        <h2>I tuoi trattamenti</h2>
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
                <div class="card-action-menu dropdown">
                  <button
                    class="btn btn-sm btn-outline-secondary card-action-menu__trigger"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Azioni trattamento"
                  >...</button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a href="/doctor/treatments/{{ $offering->id }}/edit" class="dropdown-item">Modifica trattamento</a>
                    </li>
                    <li>
                      <button
                        class="dropdown-item text-danger"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteTreatmentModal{{ $offering->id }}"
                      >Elimina trattamento</button>
                    </li>
                  </ul>
                </div>
              </div>
              <dl class="treatment-card__facts">
                <div>
                  <dt>Prezzo</dt>
                  <dd>{{ $offering->price !== null ? 'EUR '.number_format((float) $offering->price, 2, ',', '.') : 'Da definire' }}</dd>
                </div>
              </dl>
              <div class="modal fade" id="deleteTreatmentModal{{ $offering->id }}" tabindex="-1" aria-labelledby="deleteTreatmentModal{{ $offering->id }}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="POST" action="/doctor/treatments/{{ $offering->id }}">
                      @csrf
                      @method('DELETE')
                      <div class="modal-header">
                        <h2 class="modal-title fs-5" id="deleteTreatmentModal{{ $offering->id }}Label">Conferma eliminazione</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi conferma eliminazione"></button>
                      </div>
                      <div class="modal-body">
                        <p>Questo trattamento verrà rimosso. Gli appuntamenti già prenotati resteranno validi.</p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger">Sì, elimina</button>
                      </div>
                    </form>
                  </div>
                </div>
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
