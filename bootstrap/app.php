<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
      'role' => EnsureRole::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    //
  })
  ->create();

// The checked-in route cache can be owned by the web server user and drift from routes/web.php.
// Load source routes so dashboard navigation reflects the editable route file.
$app->instance('routes.cached', false);

return $app;
