<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// docs/seed: 72 materials (domain.md §8)
const ALL_SEED_MATERIALS = 72;

// flows.md §3.1: 2 closed + 1 opened (ratio 0.5), 3 committed → available −0.5
const STOCK_CLOSED = 2;
const STOCK_ON_HAND = 2.5;
const STOCK_COMMITTED = 3;
const STOCK_AVAILABLE = -0.5;

// Tempera Wallcream coverage 8/10 mq/lt
const WALLCREAM_COVERAGE_MIN = 8;

it('lists the 72 seed materials with an empty stock', function (): void {
    signInTenant();
    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CREATED);

    $response = getJson('/api/materials?per_page=100')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('meta.total', ALL_SEED_MATERIALS);

    expect($response->collect('data')->pluck('stock.on_hand')->unique()->values()->all())->toBe([0]);

    getJson('/api/materials?search=wallcream')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.package_label', 'bidone 14 lt')
        ->assertJsonPath('data.0.coverage_min', WALLCREAM_COVERAGE_MIN);

    getJson('/api/materials?kind=tool')->assertJsonPath('data.0.kind', 'tool');
});

it('creates a material together with its stock at zero', function (): void {
    signInTenant();

    $payload = [
        'name' => 'Smalto all\'acqua',
        'category' => 'enamel',
        'kind' => 'consumable_quantity',
        'base_unit' => 'lt',
        'package_size' => 2.5,
        'package_label' => 'barattolo 2,5 lt',
        'cost_price' => 32.4,
        'coverage_min' => 10,
        'coverage_max' => 12,
    ];

    postJson('/api/materials', $payload)
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.cost_price', $payload['cost_price'])
        ->assertJsonPath('data.stock.on_hand', 0)
        ->assertJsonPath('data.stock.available', 0);

    expect(Stock::query()->count())->toBe(1);
});

it('validates enums and coverage range in Italian', function (): void {
    signInTenant();

    postJson('/api/materials', [
        'name' => 'Vernice',
        'category' => 'vernice',
        'kind' => 'consumable_quantity',
        'base_unit' => 'lt',
        'package_size' => 5,
        'package_label' => 'latta 5 lt',
        'coverage_min' => 10,
        'coverage_max' => 8,
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['category', 'coverage_max']);
});

it('computes on hand, committed and available like flows.md §3.1', function (): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($material)->create(['closed_packages' => STOCK_CLOSED, 'opened_packages' => 1]);
    $accepted = quoteUsing($tenant, $material, QuoteStatus::Accepted);
    quoteUsing($tenant, $material, QuoteStatus::Draft);
    StockMovement::factory()->for($tenant)->for($material)->create(['type' => StockMovementType::Adjustment]);

    getJson("/api/materials/{$material->id}")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.stock.on_hand', STOCK_ON_HAND)
        ->assertJsonPath('data.stock.committed', STOCK_COMMITTED)
        ->assertJsonPath('data.stock.available', STOCK_AVAILABLE)
        ->assertJsonCount(1, 'data.commitments')
        ->assertJsonPath('data.commitments.0.quote.id', $accepted->id)
        ->assertJsonPath('data.commitments.0.packages', STOCK_COMMITTED)
        ->assertJsonCount(1, 'data.movements')
        ->assertJsonPath('data.movements.0.type', 'adjustment');
});

it('updates a material partially', function (): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create(['cost_price' => '47.69']);

    $patch = ['cost_price' => 48.5, 'min_stock' => 2];

    patchJson("/api/materials/{$material->id}", $patch)
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.cost_price', $patch['cost_price'])
        ->assertJsonPath('data.min_stock', $patch['min_stock'])
        ->assertJsonPath('data.name', 'Tempera Wallcream');
});

it('deletes a material only when no quote uses it', function (): void {
    $tenant = signInTenant();
    $free = Material::factory()->for($tenant)->create();
    $inDraft = Material::factory()->for($tenant)->create();
    $inClosed = Material::factory()->for($tenant)->create();
    quoteUsing($tenant, $inDraft, QuoteStatus::Draft);
    quoteUsing($tenant, $inClosed, QuoteStatus::Completed);

    deleteJson("/api/materials/{$free->id}")->assertStatus(Response::HTTP_NO_CONTENT);
    deleteJson("/api/materials/{$inDraft->id}")
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il materiale è in preventivi aperti: non si può eliminare.');
    deleteJson("/api/materials/{$inClosed->id}")
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il materiale è nei preventivi chiusi: non si può eliminare.');
});

it('hides materials of other tenants', function (): void {
    signInTenant();
    $foreign = Material::factory()->create();

    getJson("/api/materials/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    deleteJson("/api/materials/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
});
