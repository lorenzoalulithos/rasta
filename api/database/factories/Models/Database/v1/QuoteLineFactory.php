<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\JobUnit;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
final class QuoteLineFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quote_id' => static fn (array $attributes) => Quote::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'description' => 'Imbiancatura interna',
            'unit' => JobUnit::SquareMeter,
            'quantity' => '181.00',
            'coats' => 2,
            'unit_price' => '4.00',
            'amount' => '724.00',
            'sort_order' => 0,
        ];
    }
}
