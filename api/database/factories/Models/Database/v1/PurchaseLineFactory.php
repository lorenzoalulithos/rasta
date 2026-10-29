<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Material;
use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\PurchaseLine;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseLine>
 */
final class PurchaseLineFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'purchase_id' => static fn (array $attributes) => Purchase::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'material_id' => static fn (array $attributes) => Material::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'packages' => 3,
            'unit_cost' => '47.69',
        ];
    }
}
