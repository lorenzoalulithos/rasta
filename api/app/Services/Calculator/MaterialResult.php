<?php declare(strict_types=1);

namespace App\Services\Calculator;

/**
 * Requirement of one material: the snapshot of QuoteLineMaterial (domain.md §3.10).
 *
 * Quantities with 3 decimals, money with 2, as numeric strings.
 */
final readonly class MaterialResult
{
    /**
     * @param MaterialInput       $input            the material as calculated
     * @param numeric-string|null $coverageUsed     coverage used, null without coverage
     * @param numeric-string      $requiredQuantity theoretical quantity in base unit
     * @param numeric-string      $requiredPackages packages, rounded as the tenant says
     * @param numeric-string      $unitCost         cost price per package at calculation time
     * @param numeric-string      $estimatedCost    required packages × unit cost
     */
    public function __construct(
        public MaterialInput $input,
        public ?string $coverageUsed,
        public string $requiredQuantity,
        public string $requiredPackages,
        public string $unitCost,
        public string $estimatedCost,
    ) {
    }
}
