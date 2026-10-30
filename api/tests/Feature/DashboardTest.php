<?php declare(strict_types=1);

use App\Enums\MaterialKind;
use App\Enums\QuoteStatus;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

// Open quotes 1–6 (draft, sent, accepted), 7 rejected, 8 completed, 9 accepted: the home shows 9, 6, 5, 4, 3
const DASHBOARD_SHOWN_QUOTES = 5;
const DASHBOARD_LAST_NUMBER = 6;
const DASHBOARD_REJECTED_NUMBER = 7;
const DASHBOARD_COMPLETED_NUMBER = 8;
const DASHBOARD_NEWEST_NUMBER = 9;

// Accepted 20 and 15 days ago are to complete, 10 days ago is not yet
const DASHBOARD_ACCEPTED_OLDEST = 20;
const DASHBOARD_ACCEPTED_OLD = 15;
const DASHBOARD_ACCEPTED_RECENT = 10;
const DASHBOARD_TO_COMPLETE = 2;

// Below minimum: one under min_stock, one negative (committed by the accepted quote)
const DASHBOARD_MIN_STOCK = 3;
const DASHBOARD_TO_BUY = 2;

it('shows what to buy, the open quotes and the sites to close', function (): void {
    $tenant = signInTenant();
    $statuses = [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Draft, QuoteStatus::Sent];

    foreach ($statuses as $index => $status) {
        Quote::factory()->for($tenant)->create(['status' => $status, 'number' => $index + 1]);
    }

    $agesInDays = [DASHBOARD_ACCEPTED_RECENT, DASHBOARD_ACCEPTED_OLDEST];

    foreach ($agesInDays as $index => $days) {
        Quote::factory()->for($tenant)->create([
            'status' => QuoteStatus::Accepted,
            'number' => count($statuses) + $index + 1,
            'accepted_at' => Carbon::now()->subDays($days),
        ]);
    }

    Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Rejected, 'number' => DASHBOARD_REJECTED_NUMBER]);
    Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Completed, 'number' => DASHBOARD_COMPLETED_NUMBER]);

    $fissativo = Material::factory()->for($tenant)->create(['min_stock' => DASHBOARD_MIN_STOCK]);
    Stock::factory()->for($tenant)->for($fissativo)->create(['closed_packages' => 1]);
    $tempera = Material::factory()->for($tenant)->create();
    $old = quoteUsing($tenant, $tempera, QuoteStatus::Accepted);
    $old->forceFill(['number' => DASHBOARD_NEWEST_NUMBER, 'accepted_at' => Carbon::now()->subDays(DASHBOARD_ACCEPTED_OLD)])->save();
    Material::factory()->for($tenant)->create(['kind' => MaterialKind::Tool, 'min_stock' => DASHBOARD_MIN_STOCK]);
    $ok = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($ok)->create(['closed_packages' => 1]);

    $response = getJson('/api/dashboard')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.to_buy_count', DASHBOARD_TO_BUY)
        ->assertJsonCount(DASHBOARD_SHOWN_QUOTES, 'data.open_quotes')
        ->assertJsonPath('data.open_quotes.0.number', DASHBOARD_NEWEST_NUMBER)
        ->assertJsonPath('data.open_quotes.1.number', DASHBOARD_LAST_NUMBER)
        ->assertJsonCount(DASHBOARD_TO_COMPLETE, 'data.to_complete')
        ->assertJsonPath('data.to_complete.1.id', $old->id);

    expect($response->json('data.open_quotes.0.customer.name'))->toBeString()
        ->and($response->json('data.to_complete.0.number'))->toBe(DASHBOARD_LAST_NUMBER);
});

it('shows an empty home to a new tenant and nothing of the others', function (): void {
    Quote::factory()->create(['status' => QuoteStatus::Accepted, 'accepted_at' => Carbon::now()->subDays(DASHBOARD_ACCEPTED_OLDEST)]);
    signInTenant();

    getJson('/api/dashboard')
        ->assertJsonPath('data.to_buy_count', 0)
        ->assertJsonPath('data.open_quotes', [])
        ->assertJsonPath('data.to_complete', []);
});
