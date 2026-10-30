<?php declare(strict_types=1);

use App\Enums\MaterialKind;
use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// flows.md §3.1: 2 closed + 1 opened (ratio 0.5) = 2.5 on hand, 3 committed by Belissi
const STOCK_ON_HAND_EXAMPLE = 2.5;
const STOCK_COMMITTED_EXAMPLE = 3;
const STOCK_AVAILABLE_EXAMPLE = -0.5;
const STOCK_TO_BUY_EXAMPLE = 1;
const STOCK_CLOSED_EXAMPLE = 2;

// Fissativo: 1 on hand, minimum 3 → low, 2 to buy
const FISSATIVO_MIN_STOCK = 3;
const FISSATIVO_TO_BUY = 2;

// Rettifica: from 2 closed + 1 opened to 5 closed + 0 opened
const ADJUSTED_CLOSED = 5;
const ADJUSTED_CLOSED_DELTA = 3;
const ADJUSTED_OPENED_DELTA = -1;

// Stock list: 3 stocked materials (the tool is left out), 2 of them to buy
const STOCKED_MATERIALS = 3;
const MATERIALS_TO_BUY = 2;

it('commits stock only for accepted quotes', function (QuoteStatus $status, int $committed): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($material)->create(['closed_packages' => STOCK_CLOSED_EXAMPLE, 'opened_packages' => 1]);
    quoteUsing($tenant, $material, $status);

    getJson('/api/stock')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.0.on_hand', STOCK_ON_HAND_EXAMPLE)
        ->assertJsonPath('data.0.committed', $committed);
})->with([
    'accepted commits' => [QuoteStatus::Accepted, STOCK_COMMITTED_EXAMPLE],
    'draft does not' => [QuoteStatus::Draft, 0],
    'sent does not' => [QuoteStatus::Sent, 0],
    'rejected does not' => [QuoteStatus::Rejected, 0],
    'completed does not' => [QuoteStatus::Completed, 0],
]);

it('colours the materials and lists what to buy with the sites that need it', function (): void {
    $tenant = signInTenant();
    $tempera = Material::factory()->for($tenant)->create(['name' => 'Tempera Wallcream']);
    Stock::factory()->for($tenant)->for($tempera)->create(['closed_packages' => STOCK_CLOSED_EXAMPLE, 'opened_packages' => 1]);
    $quote = quoteUsing($tenant, $tempera, QuoteStatus::Accepted);
    $fissativo = Material::factory()->for($tenant)->create(['name' => 'Fissativo Idrofis', 'min_stock' => FISSATIVO_MIN_STOCK]);
    Stock::factory()->for($tenant)->for($fissativo)->create(['closed_packages' => 1]);
    $stucco = Material::factory()->for($tenant)->create(['name' => 'Stucco gesso']);
    Stock::factory()->for($tenant)->for($stucco)->create(['closed_packages' => 1]);
    Material::factory()->for($tenant)->create(['name' => 'Scala', 'kind' => MaterialKind::Tool]);

    getJson('/api/stock')
        ->assertJsonPath('meta.total', STOCKED_MATERIALS)
        ->assertJsonPath('data.0.material.name', 'Fissativo Idrofis')
        ->assertJsonPath('data.0.status', 'low')
        ->assertJsonPath('data.0.to_buy', FISSATIVO_TO_BUY)
        ->assertJsonPath('data.1.material.name', 'Stucco gesso')
        ->assertJsonPath('data.1.status', 'ok')
        ->assertJsonPath('data.1.to_buy', 0)
        ->assertJsonPath('data.2.status', 'negative')
        ->assertJsonPath('data.2.available', STOCK_AVAILABLE_EXAMPLE)
        ->assertJsonMissingPath('data.0.commitments');

    getJson('/api/stock?filter=to_buy')
        ->assertJsonPath('meta.total', MATERIALS_TO_BUY)
        ->assertJsonPath('data.1.material.name', 'Tempera Wallcream')
        ->assertJsonPath('data.1.to_buy', STOCK_TO_BUY_EXAMPLE)
        ->assertJsonPath('data.1.commitments.0.quote.id', $quote->id)
        ->assertJsonPath('data.1.commitments.0.packages', STOCK_COMMITTED_EXAMPLE);
});

it('adjusts closed and opened packages and records the delta', function (): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($material)->create(['closed_packages' => STOCK_CLOSED_EXAMPLE, 'opened_packages' => 1]);

    postJson("/api/stock/{$material->id}/adjust", ['closed' => ADJUSTED_CLOSED, 'opened' => 0, 'notes' => 'Conta in magazzino'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.closed_packages', ADJUSTED_CLOSED)
        ->assertJsonPath('data.opened_packages', 0)
        ->assertJsonPath('data.on_hand', ADJUSTED_CLOSED);

    $movement = StockMovement::query()->sole();

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->closed_delta)->toBe(ADJUSTED_CLOSED_DELTA)
        ->and($movement->opened_delta)->toBe(ADJUSTED_OPENED_DELTA)
        ->and($movement->notes)->toBe('Conta in magazzino');

    postJson("/api/stock/{$material->id}/adjust", ['closed' => ADJUSTED_CLOSED, 'opened' => 0])->assertStatus(Response::HTTP_OK);

    expect(StockMovement::query()->count())->toBe(1);

    getJson("/api/stock/movements?material_id={$material->id}")
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.type', 'adjustment')
        ->assertJsonPath('data.0.closed_delta', ADJUSTED_CLOSED_DELTA);
});

it('validates the adjustment and changes the minimum stock', function (): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($material)->create();

    postJson("/api/stock/{$material->id}/adjust", ['closed' => -1, 'opened' => 'tre'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['closed', 'opened']);

    patchJson("/api/stock/{$material->id}", ['min_stock' => FISSATIVO_MIN_STOCK])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.min_stock', FISSATIVO_MIN_STOCK)
        ->assertJsonPath('data.status', 'low')
        ->assertJsonPath('data.to_buy', FISSATIVO_MIN_STOCK);
});

it('does not touch the stock of other tenants', function (): void {
    signInTenant();
    $foreign = Material::factory()->create();

    postJson("/api/stock/{$foreign->id}/adjust", ['closed' => 1, 'opened' => 0])->assertStatus(Response::HTTP_NOT_FOUND);
    getJson('/api/stock')->assertJsonPath('meta.total', 0);
});
