<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Models\Database\v1\Material;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLineMaterial>
 */
final class QuoteLineMaterialFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quote_line_id' => static fn (array $attributes) => QuoteLine::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'material_id' => static fn (array $attributes) => Material::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'selected' => true,
            'coverage_used' => '9.00',
            'required_quantity' => '40.222',
            'required_packages' => '3.000',
            'unit_cost' => '47.69',
            'estimated_cost' => '143.07',
        ];
    }
}
