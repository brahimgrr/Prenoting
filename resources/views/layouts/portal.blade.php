@php
  $user = auth()->user();
  $role = $user?->portalRole();
  $homeRoute = $user?->portalRoute() ?? '/unsupported-role';
  $navItems = [
    'patient' => [
      ['to' => '/patient', 'label' => 'Riepilogo', 'active' => ['patient']],
      ['to' => '/patient/book', 'label' => 'Prenota visita', 'active' => ['patient/book']],
      ['to' => '/patient/appointments', 'label' => 'I miei appuntamenti', 'active' => ['patient/appointments', 'appointments/*/edit']],
      ['to' => '/patient/profile', 'label' => 'Profilo', 'active' => ['patient/profile']],
    ],
    'doctor' => [
      ['to' => '/doctor/agenda', 'label' => 'Agenda', 'active' => ['doctor/agenda']],
      ['to' => '/doctor/treatments', 'label' => 'Trattamenti', 'active' => ['doctor/treatments', 'doctor/treatments/*']],
      ['to' => '/doctor/profile', 'label' => 'Profilo', 'active' => ['doctor/profile']],
    ],
  ][$role] ?? [];
  $roleLabels = ['patient' => 'Paziente', 'doctor' => 'Medico'];
@endphp
<!doctype html>
<html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MedPortal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <div class="app-shell">
      <aside class="app-sidebar">
        <a href="{{ $homeRoute }}" class="app-brand">
          <span class="app-brand__mark">M</span>
          <span>
            <span class="app-brand__name">MedPortal</span>
            <span class="app-brand__subline">Prenotazioni</span>
          </span>
        </a>
        <nav class="app-nav" aria-label="Navigazione del portale">
          @foreach ($navItems as $item)
            @php($activePatterns = $item['active'] ?? [ltrim($item['to'], '/')])
            <a href="{{ $item['to'] }}" class="app-nav__link{{ request()->is(...$activePatterns) ? ' is-active' : '' }}">
              {{ $item['label'] }}
            </a>
          @endforeach
        </nav>
        <div class="app-sidebar__footer">
          <div class="app-user">
            <span class="app-user__avatar">{{ strtoupper(substr($user?->displayName() ?? 'U', 0, 1)) }}</span>
            <span class="app-user__meta">
              <span class="app-user__name">{{ $user?->displayName() }}</span>
              <span class="app-user__role">{{ $roleLabels[$role] ?? $role }}</span>
            </span>
          </div>
          <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="btn btn-outline-light w-100">Esci</button>
          </form>
        </div>
      </aside>

      <div class="app-main">
        <header class="app-topbar">
          <a href="{{ $homeRoute }}" class="app-brand app-brand--mobile">
            <span class="app-brand__mark">M</span>
            <span class="app-brand__name">MedPortal</span>
          </a>
          <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Esci</button>
          </form>
        </header>

        <div class="app-mobile-nav">
          <nav class="app-nav" aria-label="Navigazione mobile del portale">
            @foreach ($navItems as $item)
              @php($activePatterns = $item['active'] ?? [ltrim($item['to'], '/')])
              <a href="{{ $item['to'] }}" class="app-nav__link{{ request()->is(...$activePatterns) ? ' is-active' : '' }}">
                {{ $item['label'] }}
              </a>
            @endforeach
          </nav>
        </div>

        <main class="app-content">
          @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
          @endif
          @if ($errors->any())
            <div class="alert alert-danger" role="alert">
              {{ $errors->first() }}
            </div>
          @endif
          @yield('content')
        </main>
      </div>
    </div>
    @include('components.schedule-confirmation-modal', ['confirmation' => session('schedule_confirmation')])
    @stack('modals')
  </body>
</html>
