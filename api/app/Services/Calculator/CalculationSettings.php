<?php declare(strict_types=1);

namespace App\Services\Calculator;

use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Models\Database\v1\Tenant;

/**
 * Tenant settings that change the calculation (domain.md §3.1).
 */
final readonly class CalculationSettings
{
    /**
     * @param CoverageStrategy $coverageStrategy which coverage to use: min, avg, max
     * @param PackageRounding  $packageRounding  whole packages (up) or fractional
     */
    public function __construct(
        public CoverageStrategy $coverageStrategy = CoverageStrategy::Avg,
        public PackageRounding $packageRounding = PackageRounding::Up,
    ) {
    }

    /**
     * @param Tenant $tenant
     *
     * @return self
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->coverage_strategy, $tenant->package_rounding);
    }
}
