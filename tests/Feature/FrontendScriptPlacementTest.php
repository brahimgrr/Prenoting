<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendScriptPlacementTest extends TestCase
{
  public function test_page_specific_scripts_live_in_the_blade_that_renders_their_markup(): void
  {
    $appJs = file_get_contents(resource_path('js/app.js'));

    foreach ([
      'data-agenda-scroll-container',
      'data-working-hours-day',
      'data-comune-input',
      'data-week-url',
      'data-slot-period-filter',
      'data-auto-show-modal',
    ] as $pageSpecificHook) {
      $this->assertStringNotContainsString($pageSpecificHook, $appJs);
    }

    foreach ([
      resource_path('views/auth/register.blade.php') => 'data-comune-input',
      resource_path('views/doctor/profile.blade.php') => 'data-working-hours-day',
      resource_path('views/doctor/agenda.blade.php') => 'data-agenda-scroll-container',
      resource_path('views/patient/partials/booking-interactions.blade.php') => 'data-week-url',
      resource_path('views/components/schedule-confirmation-modal.blade.php') => 'data-auto-show-modal',
    ] as $bladePath => $scriptHook) {
      $blade = file_get_contents($bladePath);

      $this->assertStringContainsString('<script>', $blade, "{$bladePath} should contain an inline script tag.");
      $this->assertStringContainsString($scriptHook, $blade, "{$bladePath} should own the script for {$scriptHook}.");
    }
  }

  public function test_booking_slot_selection_is_enhanced_without_full_page_navigation(): void
  {
    $blade = file_get_contents(resource_path('views/patient/partials/booking-interactions.blade.php'));
    $weekPartial = file_get_contents(resource_path('views/patient/partials/booking-week-partial.blade.php'));

    $this->assertStringContainsString('const slotButton = event.target.closest(".slot-time-button")', $blade);
    $this->assertStringContainsString('fetchAndReplace(slotButton.href, ".booking-wizard", "booking-confirm")', $blade);
    $this->assertStringContainsString('"X-Requested-With": "XMLHttpRequest"', $blade);
    $this->assertStringNotContainsString('<script>', $weekPartial);
  }

  public function test_booking_period_filter_stays_on_the_page_after_click(): void
  {
    $blade = file_get_contents(resource_path('views/patient/partials/booking-interactions.blade.php'));
    $weekPartial = file_get_contents(resource_path('views/patient/partials/booking-week-partial.blade.php'));

    $this->assertStringContainsString('const filterButton = event.target.closest("[data-slot-period-filter]")', $blade);
    $this->assertStringContainsString('fetchAndReplace(filterButton.href, ".booking-wizard", "booking-step-day")', $blade);
    $this->assertStringContainsString("'date' => \$dateStr", $weekPartial);
    $this->assertStringNotContainsString("'date' => \$currentDate", $weekPartial);
  }

  public function test_booking_service_selection_is_enhanced_without_full_page_navigation(): void
  {
    $blade = file_get_contents(resource_path('views/patient/partials/booking-interactions.blade.php'));
    $wizard = file_get_contents(resource_path('views/patient/partials/booking-wizard.blade.php'));

    $this->assertStringContainsString('const serviceCard = event.target.closest(".service-choice-card[href]")', $blade);
    $this->assertStringContainsString('fetchAndReplace(serviceCard.href, ".booking-wizard", "booking-step-day")', $blade);
    $this->assertStringContainsString('"X-Requested-With": "XMLHttpRequest"', $blade);
    $this->assertStringNotContainsString('<script>', $wizard);
  }

  public function test_closure_all_day_toggle_owns_and_disables_time_fields(): void
  {
    $blade = file_get_contents(resource_path('views/doctor/agenda.blade.php'));

    $this->assertStringContainsString('data-closure-all-day-toggle', $blade);
    $this->assertStringContainsString('data-closure-time-field', $blade);
    $this->assertStringContainsString(':disabled="$closureAllDay"', $blade);
    $this->assertStringContainsString('function sincronizzaCampiOrarioChiusura', $blade);
    $this->assertStringContainsString('allDayToggle.checked = false', $blade);
  }

  public function test_inline_javascript_function_names_are_italian(): void
  {
    $inlineScriptSources = implode("\n", array_map(
      fn (string $path): string => file_get_contents($path),
      [
        resource_path('views/auth/register.blade.php'),
        resource_path('views/doctor/profile.blade.php'),
        resource_path('views/doctor/agenda.blade.php'),
      ],
    ));

    foreach ([
      'normalizzaComune',
      'nascondiSuggerimentiComune',
      'aggiornaSuggerimentiComune',
      'opzioniComuneVisibili',
      'selezionaOpzioneComune',
      'orarioInMinuti',
      'minutiGiornoOrario',
      'etichettaDurataOrario',
      'aggiornaGiornoOrario',
      'posizionaScrollAgenda',
      'aggiornaIndicatoreOraAgenda',
    ] as $italianFunctionName) {
      $this->assertStringContainsString("function {$italianFunctionName}", $inlineScriptSources);
    }

    foreach ([
      'normalizeComune',
      'hideComuneSuggestions',
      'updateComuneSuggestions',
      'visibleComuneOptions',
      'selectComuneOption',
      'halfHourTimeOptions',
      'timeSelectTemplate',
      'timeToMinutes',
      'workingHoursDayMinutes',
      'workingHoursDurationLabel',
      'updateWorkingHoursDay',
      'positionAgendaScroll',
      'tickAgendaNowMarker',
    ] as $englishFunctionName) {
      $this->assertStringNotContainsString("function {$englishFunctionName}", $inlineScriptSources);
    }
  }
}
