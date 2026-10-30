<?php declare(strict_types=1);

use App\Enums\MaterialKind;
use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// flows.md §3.3: 3 bidoni of Tempera at 48,50 (list price 47,69) + 2 Fissativo at 12,00
const PURCHASE_TEMPERA_PACKAGES = 3;
const PURCHASE_TEMPERA_COST = 48.5;
const PURCHASE_TEMPERA_LIST_PRICE = '47.69';
const PURCHASE_FISSATIVO_PACKAGES = 2;
const PURCHASE_FISSATIVO_COST = 12;
const PURCHASE_FISSATIVO_LIST_PRICE = '11.00';
const PURCHASE_TOTAL = 169.5;
const PURCHASE_LINES = 2;

// One purchase to pay and one paid of the tenant, one of another tenant
const PURCHASES_OF_TENANT = 2;

// Tempera had 2 closed + 1 opened: the purchase loads closed packages only
const PURCHASE_TEMPERA_CLOSED_BEFORE = 2;
const PURCHASE_TEMPERA_CLOSED_AFTER = 5;

it('records a purchase, loads the stock and updates only the confirmed prices', function (): void {
    $tenant = signInTenant();
    $tempera = Material::factory()->for($tenant)->create(['name' => 'Tempera Wallcream', 'cost_price' => PURCHASE_TEMPERA_LIST_PRICE]);
    Stock::factory()->for($tenant)->for($tempera)->create(['closed_packages' => PURCHASE_TEMPERA_CLOSED_BEFORE, 'opened_packages' => 1]);
    $fissativo = Material::factory()->for($tenant)->create(['name' => 'Fissativo Idrofis', 'cost_price' => PURCHASE_FISSATIVO_LIST_PRICE]);

    $response = postJson('/api/purchases', [
        'supplier' => 'Colorificio Rossi',
        'purchased_at' => '2026-10-30',
        'paid' => false,
        'lines' => [
            ['material_id' => $tempera->id, 'packages' => PURCHASE_TEMPERA_PACKAGES, 'unit_cost' => PURCHASE_TEMPERA_COST],
            ['material_id' => $fissativo->id, 'packages' => PURCHASE_FISSATIVO_PACKAGES, 'unit_cost' => PURCHASE_FISSATIVO_COST],
        ],
        'update_prices' => [$tempera->id],
    ])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.supplier', 'Colorificio Rossi')
        ->assertJsonPath('data.purchased_at', '2026-10-30')
        ->assertJsonPath('data.paid', false)
        ->assertJsonPath('data.total', PURCHASE_TOTAL)
        ->assertJsonPath('data.lines.0.material.name', 'Tempera Wallcream')
        ->assertJsonPath('data.lines.0.unit_cost', PURCHASE_TEMPERA_COST);

    expect(Stock::query()->findOrFail($tempera->id)->closed_packages)->toBe(PURCHASE_TEMPERA_CLOSED_AFTER)
        ->and(Stock::query()->findOrFail($tempera->id)->opened_packages)->toBe(1)
        ->and(Stock::query()->findOrFail($fissativo->id)->closed_packages)->toBe(PURCHASE_FISSATIVO_PACKAGES)
        ->and((string) $tempera->refresh()->cost_price)->toBe('48.50')
        ->and((string) $fissativo->refresh()->cost_price)->toBe(PURCHASE_FISSATIVO_LIST_PRICE);

    $movements = StockMovement::query()->where('purchase_id', $response->json('data.id'))->get();

    expect($movements)->toHaveCount(PURCHASE_LINES)
        ->and($movements->every(static fn (StockMovement $movement): bool => StockMovementType::Purchase === $movement->type && 0 === $movement->opened_delta))->toBeTrue()
        ->and($movements->firstWhere('material_id', $tempera->id)?->closed_delta)->toBe(PURCHASE_TEMPERA_PACKAGES);

    getJson('/api/stock')->assertJsonPath('data.1.material.name', 'Tempera Wallcream')
                         ->assertJsonPath('data.1.closed_packages', PURCHASE_TEMPERA_CLOSED_AFTER);
});

it('accepts only stocked materials of the tenant and prices of the purchased ones', function (): void {
    $tenant = signInTenant();
    $tool = Material::factory()->for($tenant)->create(['kind' => MaterialKind::Tool]);
    $archived = Material::factory()->for($tenant)->create();
    $archived->forceFill(['archived_at' => Carbon::now()])->save();
    $foreign = Material::factory()->create();
    $other = Material::factory()->for($tenant)->create();

    postJson('/api/purchases', [
        'purchased_at' => '2026-10-30',
        'paid' => true,
        'lines' => [
            ['material_id' => $tool->id, 'packages' => 1, 'unit_cost' => 1],
            ['material_id' => $archived->id, 'packages' => 1, 'unit_cost' => 1],
            ['material_id' => $foreign->id, 'packages' => 1, 'unit_cost' => 1],
            ['material_id' => $other->id, 'packages' => 0, 'unit_cost' => -1],
        ],
        'update_prices' => [$tool->id, $foreign->id],
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors([
            'lines.0.material_id', 'lines.1.material_id', 'lines.2.material_id',
            'lines.3.packages', 'lines.3.unit_cost',
        ]);

    postJson('/api/purchases', [
        'purchased_at' => '2026-10-30',
        'paid' => true,
        'lines' => [['material_id' => $other->id, 'packages' => 1, 'unit_cost' => 1]],
        'update_prices' => [$tool->id],
    ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)->assertJsonValidationErrors(['update_prices.0']);

    postJson('/api/purchases', ['paid' => false, 'lines' => []])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['purchased_at', 'lines']);

    expect(Purchase::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('lists purchases to pay and paid, and marks one as paid', function (): void {
    $tenant = signInTenant();
    $toPay = Purchase::factory()->for($tenant)->create(['supplier' => 'Colorificio Rossi', 'paid' => false]);
    Purchase::factory()->for($tenant)->create(['supplier' => 'Brico', 'paid' => true]);
    Purchase::factory()->create(['paid' => false]);

    getJson('/api/purchases?paid=0')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.supplier', 'Colorificio Rossi');
    getJson('/api/purchases?paid=1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.supplier', 'Brico');
    getJson('/api/purchases')->assertJsonPath('meta.total', PURCHASES_OF_TENANT);

    patchJson("/api/purchases/{$toPay->id}", ['paid' => true, 'notes' => 'Pagato in contanti', 'supplier' => 'Altro'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.paid', true)
        ->assertJsonPath('data.notes', 'Pagato in contanti')
        ->assertJsonPath('data.supplier', 'Colorificio Rossi');

    getJson("/api/purchases/{$toPay->id}")->assertJsonPath('data.paid', true)->assertJsonPath('data.lines', []);
});

it('does not show the purchases of other tenants', function (): void {
    signInTenant();
    $foreign = Purchase::factory()->create();

    getJson("/api/purchases/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    patchJson("/api/purchases/{$foreign->id}", ['paid' => true])->assertStatus(Response::HTTP_NOT_FOUND);
});
