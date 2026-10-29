<?php declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
                  ->withRouting(
                      web: __DIR__ . '/../routes/web.php',
                      api: __DIR__ . '/../routes/api.php',
                      commands: __DIR__ . '/../routes/console.php',
                      health: '/up',
                  )
                  ->withMiddleware(static function (Middleware $middleware): void {
                      // No login page: the PWA handles 401 itself
                      $middleware->redirectGuestsTo(
                          static fn (Request $request): ?string => $request->is('api/*') ? null : '/'
                      );
                  })
                  ->withExceptions(static function (Exceptions $exceptions): void {
                      // The API always answers JSON (401, 422, …), even without an Accept header
                      $exceptions->shouldRenderJsonWhen(
                          static fn (Request $request): bool => $request->is('api/*')
                      );
                  })->create();
