<?php declare(strict_types=1);

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
