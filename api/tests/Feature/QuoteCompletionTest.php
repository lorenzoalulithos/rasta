<?php declare(strict_types=1);

use App\Enums\MaterialKind;
use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Belissi: 3 bidoni of Tempera estimated; Stucco 1,4 packages (fractional) → 1 closed + 1 opened
const COMPLETION_TEMPERA_ESTIMATED = 3;

// Tempera: 5 + 0 in stock, used 2 closed + 1 opened → movement −3 / +1 → 2 + 1
const COMPLETION_TEMPERA_USED_CLOSED = 2;
const COMPLETION_TEMPERA_TAKEN = -3;
const COMPLETION_TEMPERA_CLOSED_AFTER = 2;
const COMPLETION_TEMPERA_ON_HAND_AFTER = 2.5;

// Stucco: 0 in stock, used 1 → the closed stop at 0 with an implicit adjustment of +1
const COMPLETION_STUCCO_MISSING = 1;

// Tempera consumption, Stucco consumption, Stucco adjustment (the unused Rullo makes none)
const COMPLETION_MOVEMENTS = 3;

// Tempera and Stucco: the unselected Fissativo is not proposed
const COMPLETION_SELECTED_MATERIALS = 2;

it('proposes the estimated consumption of the selected materials', function (): void {
    [$quote, $tempera] = acceptedSite(signInTenant());

    getJson("/api/quotes/{$quote->id}/completion")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(COMPLETION_SELECTED_MATERIALS, 'data')
        ->assertJsonPath('data.0.material.name', 'Stucco gesso')
        ->assertJsonPath('data.0.estimated_packages', COMPLETION_STUCCO_ESTIMATED)
        ->assertJsonPath('data.0.suggested_closed', 1)
        ->assertJsonPath('data.0.suggested_opened', 1)
        ->assertJsonPath('data.0.stock.closed_packages', 0)
        ->assertJsonPath('data.1.material.id', $tempera->id)
        ->assertJsonPath('data.1.estimated_packages', COMPLETION_TEMPERA_ESTIMATED)
        ->assertJsonPath('data.1.suggested_closed', COMPLETION_TEMPERA_ESTIMATED)
        ->assertJsonPath('data.1.suggested_opened', 0)
        ->assertJsonPath('data.1.stock.closed_packages', COMPLETION_TEMPERA_CLOSED_BEFORE);
});

it('closes the site: the stock goes down and the commitment disappears', function (): void {
    $tenant = signInTenant();
    [$quote, $tempera, $stucco] = acceptedSite($tenant);
    $rullo = Material::factory()->for($tenant)->create(['name' => 'Rullo', 'kind' => MaterialKind::ConsumableChecklist]);

    getJson('/api/stock?search=Tempera')->assertJsonPath('data.0.committed', COMPLETION_TEMPERA_ESTIMATED);

    postJson("/api/quotes/{$quote->id}/complete", ['consumptions' => [
        ['material_id' => $tempera->id, 'closed' => COMPLETION_TEMPERA_USED_CLOSED, 'opened' => 1],
        ['material_id' => $stucco->id, 'closed' => 1, 'opened' => 0],
        ['material_id' => $rullo->id, 'closed' => 0, 'opened' => 0],
    ]])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('adjustments.0.material_id', $stucco->id)
        ->assertJsonPath('adjustments.0.closed_delta', COMPLETION_STUCCO_MISSING)
        ->assertJsonCount(1, 'adjustments');

    expect($quote->refresh()->completed_at)->toBeInstanceOf(Carbon::class)
        ->and(Stock::query()->findOrFail($tempera->id)->closed_packages)->toBe(COMPLETION_TEMPERA_CLOSED_AFTER)
        ->and(Stock::query()->findOrFail($tempera->id)->opened_packages)->toBe(1)
        ->and(Stock::query()->findOrFail($stucco->id)->closed_packages)->toBe(0)
        ->and(StockMovement::query()->count())->toBe(COMPLETION_MOVEMENTS);

    $consumption = StockMovement::query()->where('material_id', $tempera->id)->sole();

    expect($consumption->type)->toBe(StockMovementType::Consumption)
        ->and($consumption->closed_delta)->toBe(COMPLETION_TEMPERA_TAKEN)
        ->and($consumption->opened_delta)->toBe(1)
        ->and($consumption->quote_id)->toBe($quote->id);

    $adjustment = StockMovement::query()->where('material_id', $stucco->id)->where('type', StockMovementType::Adjustment)->sole();

    expect($adjustment->closed_delta)->toBe(COMPLETION_STUCCO_MISSING);

    getJson('/api/stock?search=Tempera')->assertJsonPath('data.0.committed', 0)
                                        ->assertJsonPath('data.0.on_hand', COMPLETION_TEMPERA_ON_HAND_AFTER);
});

it('closes only an accepted quote, once', function (QuoteStatus $status): void {
    $tenant = signInTenant();
    $material = Material::factory()->for($tenant)->create();
    $quote = quoteUsing($tenant, $material, $status);

    getJson("/api/quotes/{$quote->id}/completion")->assertStatus(Response::HTTP_CONFLICT);
    postJson("/api/quotes/{$quote->id}/complete", ['consumptions' => []])
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', "Il preventivo è {$status->label()}: si chiude solo un cantiere accettato.");
})->with([
    'draft' => [QuoteStatus::Draft],
    'sent' => [QuoteStatus::Sent],
    'rejected' => [QuoteStatus::Rejected],
    'completed' => [QuoteStatus::Completed],
]);

it('validates the consumptions', function (): void {
    $tenant = signInTenant();
    [$quote, $tempera] = acceptedSite($tenant);
    $tool = Material::factory()->for($tenant)->create(['kind' => MaterialKind::Tool]);
    $foreign = Material::factory()->create();

    postJson("/api/quotes/{$quote->id}/complete", ['consumptions' => [
        ['material_id' => $tempera->id, 'closed' => -1, 'opened' => 'una'],
        ['material_id' => $tempera->id, 'closed' => 1, 'opened' => 0],
        ['material_id' => $tool->id, 'closed' => 1, 'opened' => 0],
        ['material_id' => $foreign->id, 'closed' => 1, 'opened' => 0],
    ]])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors([
            'consumptions.0.closed', 'consumptions.0.opened', 'consumptions.1.material_id',
            'consumptions.2.material_id', 'consumptions.3.material_id',
        ]);

    expect($quote->refresh()->status)->toBe(QuoteStatus::Accepted)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('does not close the sites of other tenants', function (): void {
    signInTenant();
    $quote = Quote::factory()->create(['status' => QuoteStatus::Accepted]);

    getJson("/api/quotes/{$quote->id}/completion")->assertStatus(Response::HTTP_NOT_FOUND);
    postJson("/api/quotes/{$quote->id}/complete", ['consumptions' => []])->assertStatus(Response::HTTP_NOT_FOUND);
});
