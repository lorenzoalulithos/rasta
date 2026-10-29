<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\BaseUnit;
use App\Enums\MaterialCategory;
use App\Enums\MaterialKind;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
final class MaterialFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Tempera Wallcream',
            'category' => MaterialCategory::PaintInterior,
            'kind' => MaterialKind::ConsumableQuantity,
            'base_unit' => BaseUnit::Liter,
            'package_size' => '14.000',
            'package_label' => 'bidone 14 lt',
            'cost_price' => '47.69',
            'coverage_min' => '8.00',
            'coverage_max' => '10.00',
            'min_stock' => '0.00',
        ];
    }
}
