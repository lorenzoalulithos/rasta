<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'city' => fake()->city(),
        ];
    }
}
