<?php declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TenantCatalogController;
use App\Http\Controllers\TenantController;
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

    Route::get('/tenant', [TenantController::class, 'show'])->name('tenant.show');
    Route::patch('/tenant', [TenantController::class, 'update'])->name('tenant.update');
    Route::post('/tenant/seed-catalog', [TenantCatalogController::class, 'seed'])->name('tenant.seed-catalog');

    Route::post('/jobs/reorder', [JobController::class, 'reorder'])->name('jobs.reorder');
    Route::apiResource('jobs', JobController::class);
    Route::apiResource('materials', MaterialController::class);

    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/sites', [SiteController::class, 'index'])->name('customers.sites.index');
    Route::post('/customers/{customer}/sites', [SiteController::class, 'store'])->name('customers.sites.store');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
});
