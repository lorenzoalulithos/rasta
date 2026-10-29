<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
final class SiteFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'name' => 'Vano scala',
        ];
    }
}
