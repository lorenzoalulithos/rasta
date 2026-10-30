<?php declare(strict_types=1);

use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use App\Services\Quotes\QuoteNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Years of the numbering tests
const NUMBERING_YEAR = 2026;
const NEXT_YEAR = 2027;

// Second and third quote of the year
const SECOND_NUMBER = 2;
const THIRD_NUMBER = 3;

it('numbers quotes 1, 2, 3 per tenant and year', function (): void {
    $numbering = new QuoteNumbering();
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    $numbers = DB::transaction(static fn (): array => [
        $numbering->next($tenant, NUMBERING_YEAR),
        $numbering->next($tenant, NUMBERING_YEAR),
        $numbering->next($other, NUMBERING_YEAR),
        $numbering->next($tenant, NEXT_YEAR),
    ]);

    expect($numbers)->toBe([1, SECOND_NUMBER, 1, 1]);
});

it('never reuses the number of a deleted quote', function (): void {
    $tenant = signInTenant();
    $site = Site::factory()->for($tenant)->create();

    postJson('/api/quotes', ['site_id' => $site->id])->assertJsonPath('data.number', 1);
    $second = postJson('/api/quotes', ['site_id' => $site->id])->assertJsonPath('data.number', SECOND_NUMBER);

    deleteJson('/api/quotes/' . $second->json('data.id'))->assertStatus(Response::HTTP_NO_CONTENT);

    postJson('/api/quotes', ['site_id' => $site->id])->assertJsonPath('data.number', THIRD_NUMBER);
});

it('refuses to number outside a transaction', function (): void {
    // RefreshDatabase wraps the test in a transaction: leave it to call next() outside one
    DB::rollBack();

    new QuoteNumbering()->next(Tenant::factory()->make(['id' => 'x']), NUMBERING_YEAR);
})->throws(LogicException::class);
