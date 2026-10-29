<?php declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TenantCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', static fn () => response()->json([
    'data' => [
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ],
]))->name('ping');

Route::post('/auth/login', [AuthController::class, 'login'])
     ->middleware('throttle:5,1')
     ->name('auth.login');

Route::middleware('auth:sanctum')->group(static function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::post('/tenant/seed-catalog', [TenantCatalogController::class, 'seed'])->name('tenant.seed-catalog');
});
