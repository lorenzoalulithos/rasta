<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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

// Chiusura cantiere (QuoteCompletionTest): Tempera 5 + 0 in stock, Stucco estimated 1,4 packages
const COMPLETION_TEMPERA_CLOSED_BEFORE = 5;
const COMPLETION_STUCCO_ESTIMATED = 1.4;

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

/**
 * The real Belissi quote (domain.md §7.3) with private notes that must never be printed.
 *
 * @return Quote
 */
function belissiQuote(): Quote
{
    Carbon::setTestNow('2026-01-10 09:00:00');
    $tenant = signInTenant([
        'name' => 'Ravizza Patrick',
        'quote_footer_text' => 'Operazione senza applicazione Iva, regime forfettario',
        'default_payment_terms' => 'Rimessa diretta',
    ]);
    postJson('/api/tenant/seed-catalog');
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi', 'address' => 'Via Roma 1', 'zip' => '38067', 'city' => 'Ledro', 'province' => 'TN']);
    $site = Site::factory()->for($tenant)->for($customer)->create(['name' => 'Vano scala', 'address' => null]);
    $id = (string) postJson('/api/quotes', ['site_id' => $site->id])->json('data.id');

    postJson("/api/quotes/{$id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'description' => 'Imbiancatura vano scala interno a due mani con pittura a tempera', 'quantity' => BELISSI_SQM]);
    postJson("/api/quotes/{$id}/lines", ['job_id' => seedJob('Stuccature')->id, 'description' => 'Stuccature interne', 'quantity' => STUCCATURE_HOURS]);
    postJson("/api/quotes/{$id}/lines", ['description' => 'Carteggiatura e tinteggiatura portone esterno con smalto in tinta', 'quantity' => 1, 'unit_price' => PORTONE_PRICE]);

    $quote = Quote::query()->whereKey($id)->firstOrFail();
    $quote->forceFill(['private_notes' => 'Chiavi dal portinaio'])->save();

    return $quote;
}

/**
 * Accepted quote with Tempera (3 packages) and Stucco (1,4), plus an unselected Fissativo.
 *
 * @param Tenant $tenant
 *
 * @return array{0: Quote, 1: Material, 2: Material}
 */
function acceptedSite(Tenant $tenant): array
{
    $tempera = Material::factory()->for($tenant)->create(['name' => 'Tempera Wallcream']);
    Stock::factory()->for($tenant)->for($tempera)->create(['closed_packages' => COMPLETION_TEMPERA_CLOSED_BEFORE]);
    $quote = quoteUsing($tenant, $tempera, QuoteStatus::Accepted);

    $stucco = Material::factory()->for($tenant)->create(['name' => 'Stucco gesso']);
    $line = QuoteLine::factory()->for($tenant)->for($quote)->create();
    QuoteLineMaterial::factory()->for($tenant)->for($line, 'line')->for($stucco)->create(['required_packages' => COMPLETION_STUCCO_ESTIMATED]);
    $fissativo = Material::factory()->for($tenant)->create(['name' => 'Fissativo Idrofis']);
    QuoteLineMaterial::factory()->for($tenant)->for($line, 'line')->for($fissativo)->create(['selected' => false]);

    return [$quote, $tempera, $stucco];
}
