<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\StockMovement;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'material_id' => static fn (array $attributes) => Material::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'type' => StockMovementType::Adjustment,
            'closed_delta' => 1,
            'opened_delta' => 0,
            'occurred_at' => '2026-01-08 08:00:00',
        ];
    }
}
