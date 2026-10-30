<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\postJson;

// Belissi, domain.md §7.3
const BELISSI_SQM = 181;
const BELISSI_TOTAL = 974;
const BELISSI_COST = 143.07;
const BELISSI_MARGIN = 830.93;
const BELISSI_MARGIN_PCT = 85.3;
const BELISSI_PAINT_AMOUNT = 724;
const BELISSI_PAINT_PACKAGES = 3;
const BELISSI_PAINT_LITERS = 40.222;
const STUCCATURE_HOURS = 2;
const STUCCATURE_AMOUNT = 100;
const PORTONE_PRICE = 150;

/**
 * Authenticates a new user of a new tenant with a Sanctum token and returns the tenant.
 *
 * A helper instead of `$this->tenant`: PHPStan does not know Pest's `$this`.
 *
 * @param array<string, mixed> $attributes tenant attributes
 *
 * @return Tenant
 */
function signInTenant(array $attributes = []): Tenant
{
    $tenant = Tenant::factory()->create($attributes);
    Sanctum::actingAs(User::factory()->for($tenant)->create());

    return $tenant;
}

/**
 * A quote of the tenant in the given status with one line that selects the material.
 *
 * @param Tenant      $tenant
 * @param Material    $material
 * @param QuoteStatus $status
 *
 * @return Quote
 */
function quoteUsing(Tenant $tenant, Material $material, QuoteStatus $status): Quote
{
    $quote = Quote::factory()->for($tenant)->create(['status' => $status]);
    $line = QuoteLine::factory()->for($tenant)->for($quote)->create();
    QuoteLineMaterial::factory()->for($tenant)->for($line, 'line')->for($material)->create();

    return $quote;
}

/**
 * Tenant with the seed catalog and an empty draft for Condominio Belissi.
 *
 * @return array{0: Tenant, 1: Quote}
 */
function belissiDraft(): array
{
    $tenant = signInTenant();
    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CREATED);
    $site = Site::factory()->for($tenant)->create(['name' => 'Vano scala']);
    $quote = Quote::query()->whereKey((string) postJson('/api/quotes', ['site_id' => $site->id])->json('data.id'))->firstOrFail();

    return [$tenant, $quote];
}

/**
 * @param string $name
 *
 * @return Job
 */
function seedJob(string $name): Job
{
    return Job::query()->where('name', $name)->firstOrFail();
}
