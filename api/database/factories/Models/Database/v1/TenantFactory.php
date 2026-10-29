<?php declare(strict_types=1);

namespace Database\Factories\Models\Database\v1;

use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Enums\TaxRegime;
use App\Enums\Trade;
use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /**
     * {@inheritDoc}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'zip' => fake()->numerify('#####'),
            'city' => fake()->city(),
            'province' => 'BG',
            'vat_number' => fake()->numerify('###########'),
            'email' => fake()->companyEmail(),
            'tax_regime' => TaxRegime::Forfettario,
            'default_vat_rate' => '0.00',
            'quote_validity_days' => 90,
            'coverage_strategy' => CoverageStrategy::Avg,
            'opened_package_ratio' => '0.50',
            'package_rounding' => PackageRounding::Up,
            'trade' => Trade::Painter,
        ];
    }
}
