<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendCssResponsibilityTest extends TestCase
{
  public function test_simple_css_modules_do_not_own_bootstrap_layout_responsibilities(): void
  {
    $layoutTokens = [
      '@media',
      'grid-template-columns',
      'display: grid',
      'display: flex',
      'align-items:',
      'justify-content:',
      'gap:',
      'padding:',
      'margin:',
    ];

    foreach ([
      'resources/css/patient-dashboard.css',
      'resources/css/appointments.css',
      'resources/css/booking.css',
      'resources/css/profile.css',
      'resources/css/treatments.css',
    ] as $cssPath) {
      $css = file_get_contents(base_path($cssPath));

      foreach ($layoutTokens as $layoutToken) {
        $this->assertStringNotContainsString($layoutToken, $css, "{$cssPath} should leave {$layoutToken} to Bootstrap utilities.");
      }
    }
  }

  public function test_responsive_layout_is_not_kept_in_custom_css(): void
  {
    $this->assertFileDoesNotExist(resource_path('css/responsive.css'));
    $this->assertStringNotContainsString('./responsive.css', file_get_contents(resource_path('css/app.css')));
  }

  public function test_scrollable_week_strips_can_shrink_inside_portal_layout(): void
  {
    $bookingCss = file_get_contents(resource_path('css/booking.css'));

    $this->assertMatchesRegularExpression('/\.week-strip\s*\{[^}]*min-width:\s*0;/s', $bookingCss);
  }

  public function test_portal_main_column_can_shrink_next_to_desktop_sidebar(): void
  {
    $portalCss = file_get_contents(resource_path('css/portal-layout.css'));

    $this->assertMatchesRegularExpression('/\.app-main\s*\{[^}]*min-width:\s*0;/s', $portalCss);
  }

  public function test_portal_page_headings_use_bootstrap_spacing_from_following_content(): void
  {
    foreach ([
      'resources/views/doctor/agenda.blade.php',
      'resources/views/doctor/profile.blade.php',
      'resources/views/doctor/treatments.blade.php',
      'resources/views/patient/appointment-edit.blade.php',
      'resources/views/patient/appointments.blade.php',
      'resources/views/patient/booking.blade.php',
      'resources/views/patient/dashboard.blade.php',
      'resources/views/patient/profile.blade.php',
    ] as $viewPath) {
      $view = file_get_contents(base_path($viewPath));
      if (str_contains($view, "patient.partials.booking-page")) {
        $view .= "\n".file_get_contents(resource_path('views/patient/partials/booking-page.blade.php'));
      }

      preg_match_all('/class="([^"]*\bportal-page-heading\b[^"]*)"/', $view, $matches);

      $this->assertNotEmpty($matches[1], "{$viewPath} should render a portal page heading.");

      foreach ($matches[1] as $classList) {
        $this->assertStringContainsString('mb-4', $classList, "{$viewPath} should keep page headings separated with Bootstrap spacing.");
      }
    }
  }
}
