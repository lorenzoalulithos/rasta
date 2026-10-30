<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Belissi needs 3 bidoni of Tempera; the stock has 2 (domain.md §7.3)
const TEMPERA_IN_STOCK = 2;
const TEMPERA_AVAILABLE_AFTER = -1;
const TEMPERA_TO_BUY = 1;

// Tempera price raised before duplicating: 3 × 50.00
const DUPLICATE_TEMPERA_PRICE = 50;
const DUPLICATE_TEMPERA_COST = 150;
const SECOND_QUOTE_NUMBER = 2;

dataset('allowed transitions', [
    'draft → sent' => [QuoteStatus::Draft, 'sent'],
    'draft → rejected' => [QuoteStatus::Draft, 'rejected'],
    'sent → accepted' => [QuoteStatus::Sent, 'accepted'],
    'sent → rejected' => [QuoteStatus::Sent, 'rejected'],
    'sent → draft' => [QuoteStatus::Sent, 'draft'],
]);

dataset('forbidden transitions', [
    'draft → accepted' => [QuoteStatus::Draft, 'accepted', 'Prima segna il preventivo come inviato.'],
    'accepted → draft' => [QuoteStatus::Accepted, 'draft', 'Il preventivo è accettato: si chiude con la chiusura cantiere.'],
    'rejected → draft' => [QuoteStatus::Rejected, 'draft', 'Un preventivo rifiutato non torna in bozza: duplicalo.'],
    'completed → sent' => [QuoteStatus::Completed, 'sent', 'Il preventivo è chiuso: non può passare a inviato.'],
    'sent → sent' => [QuoteStatus::Sent, 'sent', 'Il preventivo è inviato: non può passare a inviato.'],
]);

it('allows the transitions of domain.md §6', function (QuoteStatus $from, string $to): void {
    $tenant = signInTenant();
    $quote = Quote::factory()->for($tenant)->create(['status' => $from]);
    QuoteLine::factory()->for($tenant)->for($quote)->create();

    postJson("/api/quotes/{$quote->id}/transition", ['to' => $to])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.status', $to);
})->with('allowed transitions');

it('refuses the other transitions with 409', function (QuoteStatus $from, string $to, string $message): void {
    $tenant = signInTenant();
    $quote = Quote::factory()->for($tenant)->create(['status' => $from]);
    QuoteLine::factory()->for($tenant)->for($quote)->create();

    postJson("/api/quotes/{$quote->id}/transition", ['to' => $to])
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', $message);

    expect($quote->refresh()->status)->toBe($from);
})->with('forbidden transitions');

it('does not send an empty quote', function (): void {
    $quote = Quote::factory()->for(signInTenant())->create();

    postJson("/api/quotes/{$quote->id}/transition", ['to' => 'sent'])
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Aggiungi almeno un lavoro prima di inviare il preventivo.');
});

it('accepts, commits the stock and warns about what is missing', function (): void {
    [$tenant, $quote] = belissiDraft();
    postJson("/api/quotes/{$quote->id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'quantity' => BELISSI_SQM]);
    $tempera = Material::query()->where('name', 'Tempera Wallcream')->firstOrFail();
    Stock::query()->whereKey($tempera->id)->update(['closed_packages' => TEMPERA_IN_STOCK]);

    postJson("/api/quotes/{$quote->id}/transition", ['to' => 'sent'])->assertJsonPath('stock_warnings', []);

    postJson("/api/quotes/{$quote->id}/transition", ['to' => 'accepted'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonCount(1, 'stock_warnings')
        ->assertJsonPath('stock_warnings.0.name', 'Tempera Wallcream')
        ->assertJsonPath('stock_warnings.0.available', TEMPERA_AVAILABLE_AFTER)
        ->assertJsonPath('stock_warnings.0.to_buy', TEMPERA_TO_BUY);

    expect($quote->refresh()->accepted_at)->not->toBeNull()
        ->and($tenant->id)->toBe($quote->tenant_id);

    getJson("/api/materials/{$tempera->id}")
        ->assertJsonPath('data.stock.committed', BELISSI_PAINT_PACKAGES)
        ->assertJsonPath('data.commitments.0.quote.id', $quote->id);
});

it('duplicates into a new draft with the lines recalculated on today\'s catalog', function (): void {
    [, $quote] = belissiDraft();
    postJson("/api/quotes/{$quote->id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'quantity' => BELISSI_SQM]);
    postJson("/api/quotes/{$quote->id}/lines", ['description' => 'Portone', 'quantity' => 1, 'unit_price' => PORTONE_PRICE]);
    $quote->forceFill(['status' => QuoteStatus::Rejected, 'private_notes' => 'Troppo caro'])->save();
    Material::query()->where('name', 'Tempera Wallcream')->update(['cost_price' => DUPLICATE_TEMPERA_PRICE]);

    $copy = postJson("/api/quotes/{$quote->id}/duplicate")
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.number', SECOND_QUOTE_NUMBER)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.private_notes', null)
        ->assertJsonPath('data.lines.1.description', 'Portone')
        ->assertJsonPath('data.lines.0.materials.0.unit_cost', DUPLICATE_TEMPERA_PRICE)
        ->assertJsonPath('data.estimated_cost', DUPLICATE_TEMPERA_COST)
        ->assertJsonPath('data.subtotal', BELISSI_PAINT_AMOUNT + PORTONE_PRICE);

    expect($copy->json('data.id'))->not->toBe($quote->id)
        ->and($quote->refresh()->status)->toBe(QuoteStatus::Rejected)
        ->and((float) $quote->estimated_cost)->toBe(BELISSI_COST);
});
