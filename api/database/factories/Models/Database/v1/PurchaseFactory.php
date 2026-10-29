<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
final class PurchaseFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'supplier' => fake()->company(),
            'purchased_at' => '2026-01-08',
            'paid' => false,
        ];
    }
}
