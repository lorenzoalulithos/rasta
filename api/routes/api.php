<?php declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QuoteCalculationController;
use App\Http\Controllers\QuoteCompletionController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLineController;
use App\Http\Controllers\QuoteStatusController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StockController;
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

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/tenant', [TenantController::class, 'show'])->name('tenant.show');
    Route::patch('/tenant', [TenantController::class, 'update'])->name('tenant.update');
    Route::post('/tenant/seed-catalog', [TenantCatalogController::class, 'seed'])->name('tenant.seed-catalog');

    Route::post('/jobs/reorder', [JobController::class, 'reorder'])->name('jobs.reorder');
    Route::apiResource('jobs', JobController::class);
    Route::apiResource('materials', MaterialController::class);

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
    Route::patch('/stock/{material}', [StockController::class, 'update'])->name('stock.update');
    Route::post('/stock/{material}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    Route::apiResource('purchases', PurchaseController::class)->except('destroy');

    Route::apiResource('quotes', QuoteController::class);
    Route::scopeBindings()->group(static function (): void {
        Route::post('/quotes/{quote}/lines/reorder', [QuoteLineController::class, 'reorder'])->name('quotes.lines.reorder');
        Route::post('/quotes/{quote}/lines', [QuoteLineController::class, 'store'])->name('quotes.lines.store');
        Route::patch('/quotes/{quote}/lines/{line}', [QuoteLineController::class, 'update'])->name('quotes.lines.update');
        Route::delete('/quotes/{quote}/lines/{line}', [QuoteLineController::class, 'destroy'])->name('quotes.lines.destroy');
    });
    Route::post('/quotes/{quote}/transition', [QuoteStatusController::class, 'transition'])->name('quotes.transition');
    Route::post('/quotes/{quote}/duplicate', [QuoteStatusController::class, 'duplicate'])->name('quotes.duplicate');
    Route::get('/quotes/{quote}/pdf', [QuoteStatusController::class, 'pdf'])->name('quotes.pdf');
    Route::post('/quotes/{quote}/calculate', [QuoteCalculationController::class, 'calculate'])->name('quotes.calculate');
    Route::post('/quotes/{quote}/preview-calculate', [QuoteCalculationController::class, 'preview'])->name('quotes.preview-calculate');
    Route::get('/quotes/{quote}/completion', [QuoteCompletionController::class, 'show'])->name('quotes.completion');
    Route::post('/quotes/{quote}/complete', [QuoteCompletionController::class, 'complete'])->name('quotes.complete');

    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/sites', [SiteController::class, 'index'])->name('customers.sites.index');
    Route::post('/customers/{customer}/sites', [SiteController::class, 'store'])->name('customers.sites.store');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
});
