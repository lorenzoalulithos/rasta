<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\JobMaterial;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobMaterial>
 */
final class JobMaterialFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'job_id' => static fn (array $attributes) => Job::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'material_id' => static fn (array $attributes) => Material::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'role' => JobMaterialRole::Primary,
            'consumption_mode' => ConsumptionMode::Coverage,
            'apply_coats' => true,
            'selected_by_default' => true,
            'sort_order' => 0,
        ];
    }
}
