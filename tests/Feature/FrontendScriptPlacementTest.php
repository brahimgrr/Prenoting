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
      'data-working-hours-row',
      'data-comune-input',
      'data-week-url',
      'data-slot-period-filter',
      'data-auto-show-modal',
    ] as $pageSpecificHook) {
      $this->assertStringNotContainsString($pageSpecificHook, $appJs);
    }

    foreach ([
      resource_path('views/auth/register.blade.php') => 'data-comune-input',
      resource_path('views/doctor/profile.blade.php') => 'data-working-hours-row',
      resource_path('views/doctor/agenda.blade.php') => 'data-agenda-scroll-container',
      resource_path('views/patient/partials/booking-week-partial.blade.php') => 'data-week-url',
      resource_path('views/components/schedule-confirmation-modal.blade.php') => 'data-auto-show-modal',
    ] as $bladePath => $scriptHook) {
      $blade = file_get_contents($bladePath);

      $this->assertStringContainsString('<script>', $blade, "{$bladePath} should contain an inline script tag.");
      $this->assertStringContainsString($scriptHook, $blade, "{$bladePath} should own the script for {$scriptHook}.");
    }
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
      'opzioniOrarieMezzOra',
      'templateSelettoreOrario',
      'orarioInMinuti',
      'minutiInOrario',
      'aggiungiMinutiAOrario',
      'suggerisciFineOrario',
      'fineOrarioPrecedente',
      'valoriPredefinitiNuovaFasciaOraria',
      'templateFasciaOraria',
      'fasceOrarieConValori',
      'minutiGiornoOrario',
      'etichettaDurataOrario',
      'impostaGiornoOrarioAperto',
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
      'minutesToTime',
      'addMinutesToTime',
      'suggestedWorkingHoursEnd',
      'previousWorkingHoursEnd',
      'workingHoursDefaultsForNewRow',
      'workingHoursRowTemplate',
      'workingHoursRowsHaveValues',
      'workingHoursDayMinutes',
      'workingHoursDurationLabel',
      'setWorkingHoursDayOpen',
      'positionAgendaScroll',
      'tickAgendaNowMarker',
    ] as $englishFunctionName) {
      $this->assertStringNotContainsString("function {$englishFunctionName}", $inlineScriptSources);
    }
  }
}
