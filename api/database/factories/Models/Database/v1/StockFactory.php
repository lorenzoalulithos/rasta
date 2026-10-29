<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Material;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
final class StockFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'material_id' => static fn (array $attributes) => Material::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'closed_packages' => 0,
            'opened_packages' => 0,
        ];
    }
}
