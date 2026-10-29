<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
final class QuoteFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'site_id' => static fn (array $attributes) => Site::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'number' => fake()->unique()->numberBetween(1, 9999),
            'year' => 2026,
            'issued_at' => '2026-01-10',
            'expires_at' => '2026-04-10',
            'status' => QuoteStatus::Draft,
            'vat_rate' => '0.00',
        ];
    }
}
