<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Laravel\Sanctum\Sanctum;

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
