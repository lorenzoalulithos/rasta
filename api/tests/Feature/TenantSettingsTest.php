<?php declare(strict_types=1);

use App\Enums\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

uses(RefreshDatabase::class);

// Settings changed in the test (flows.md §7)
const PATCHED_VALIDITY_DAYS = 60;
const PATCHED_OPENED_RATIO = 0.25;

it('shows the company settings', function (): void {
    signInTenant(['name' => 'Ravizza Patrick']);

    getJson('/api/tenant')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.name', 'Ravizza Patrick')
        ->assertJsonPath('data.tax_regime', 'forfettario')
        ->assertJsonPath('data.package_rounding', 'up')
        ->assertJsonPath('data.trade', 'painter');
});

it('updates only the fields sent', function (): void {
    $tenant = signInTenant(['name' => 'Ravizza Patrick']);

    patchJson('/api/tenant', [
        'quote_validity_days' => PATCHED_VALIDITY_DAYS,
        'coverage_strategy' => 'max',
        'opened_package_ratio' => PATCHED_OPENED_RATIO,
        'default_payment_terms' => 'Rimessa diretta',
        'trade' => 'plumber',
    ])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.quote_validity_days', PATCHED_VALIDITY_DAYS)
        ->assertJsonPath('data.coverage_strategy', 'max')
        ->assertJsonPath('data.opened_package_ratio', PATCHED_OPENED_RATIO)
        ->assertJsonPath('data.default_payment_terms', 'Rimessa diretta')
        ->assertJsonPath('data.name', 'Ravizza Patrick');

    expect($tenant->refresh()->trade)->toBe(Trade::Painter);
});

it('rejects values outside the allowed ones', function (): void {
    signInTenant(['name' => 'Ravizza Patrick']);

    patchJson('/api/tenant', ['coverage_strategy' => 'media', 'opened_package_ratio' => 2])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors([
            'coverage_strategy' => 'Il valore di resa usata non è tra quelli ammessi.',
            'opened_package_ratio' => 'Il campo valore confezione aperta non può superare 1.',
        ]);
});
