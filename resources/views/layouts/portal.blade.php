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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <div class="app-shell container-fluid p-0">
      <div class="row g-0 min-vh-100">
      <aside class="app-sidebar col-lg-3 col-xxl-2 d-none d-lg-flex flex-column vh-100 overflow-auto position-sticky top-0 p-3">
        <a href="{{ $homeRoute }}" class="app-brand d-flex align-items-center gap-2 text-decoration-none">
          <span class="app-brand__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">M</span>
          <span>
            <span class="app-brand__name d-block">MedPortal</span>
            <span class="app-brand__subline d-block">Prenotazioni</span>
          </span>
        </a>
        <nav class="app-nav d-grid gap-1 mt-4">
          @foreach ($navItems as $item)
            @php($activePatterns = $item['active'] ?? [ltrim($item['to'], '/')])
            <a href="{{ $item['to'] }}" class="app-nav__link py-2 px-3 text-decoration-none{{ request()->is(...$activePatterns) ? ' is-active' : '' }}">
              {{ $item['label'] }}
            </a>
          @endforeach
        </nav>
        <div class="app-sidebar__footer d-grid gap-3 mt-auto">
          <div class="app-user d-flex align-items-center gap-2">
            <span class="app-user__avatar d-inline-flex align-items-center justify-content-center flex-shrink-0">{{ strtoupper(substr($user?->displayName() ?? 'U', 0, 1)) }}</span>
            <span class="app-user__meta d-grid">
              <span class="app-user__name d-block">{{ $user?->displayName() }}</span>
              <span class="app-user__role d-block">{{ $roleLabels[$role] ?? $role }}</span>
            </span>
          </div>
          <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="btn btn-outline-light w-100">Esci</button>
          </form>
        </div>
      </aside>

      <div class="app-main col d-flex flex-column min-vh-100">
        <header class="app-topbar d-flex d-lg-none align-items-center justify-content-between px-3 py-2">
          <a href="{{ $homeRoute }}" class="app-brand app-brand--mobile d-flex align-items-center gap-2 text-decoration-none">
            <span class="app-brand__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">M</span>
            <span class="app-brand__name d-block">MedPortal</span>
          </a>
          <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Esci</button>
          </form>
        </header>

        <div class="app-mobile-nav d-block d-lg-none overflow-auto px-3 py-2">
          <nav class="app-nav d-flex gap-2 flex-nowrap">
            @foreach ($navItems as $item)
              @php($activePatterns = $item['active'] ?? [ltrim($item['to'], '/')])
              <a href="{{ $item['to'] }}" class="app-nav__link py-2 px-3 text-decoration-none flex-shrink-0{{ request()->is(...$activePatterns) ? ' is-active' : '' }}">
                {{ $item['label'] }}
              </a>
            @endforeach
          </nav>
        </div>

        <main class="app-content flex-grow-1 p-3 p-md-4">
          @if (session('status'))
            <div id="status-alert" class="alert alert-success" role="status">{{ session('status') }}</div>
            <script>
                setTimeout(() => document.getElementById('status-alert')?.remove(), 3000);
            </script>
          @endif
          @if ($errors->any())
            <div id="error-alert" class="alert alert-danger" role="alert">
              {{ $errors->first() }}
            </div>
            <script>
                setTimeout(() => document.getElementById('error-alert')?.remove(), 3000);
            </script>
          @endif
          @yield('content')
        </main>
      </div>
      </div>
    </div>
    @include('components.schedule-confirmation-modal', ['confirmation' => session('schedule_confirmation')])
    @stack('modals')
  </body>
</html>
