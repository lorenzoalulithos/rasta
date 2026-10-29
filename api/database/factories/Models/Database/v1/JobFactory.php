<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\JobUnit;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
final class JobFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Imbiancatura interna',
            'unit' => JobUnit::SquareMeter,
            'unit_price' => '4.00',
            'default_coats' => 2,
            'sort_order' => 0,
            'active' => true,
        ];
    }
}
