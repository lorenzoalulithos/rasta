<?php declare(strict_types=1);

namespace App\Services\Calculator;

/**
 * A calculated line: amount for the customer, material requirement and cost.
 */
final readonly class LineResult
{
    /**
     * @param numeric-string        $amount        quantity × unit price
     * @param array<MaterialResult> $materials     one per input material, selected or not
     * @param numeric-string        $estimatedCost sum of the selected materials only
     * @param numeric-string|null   $hours         estimated hours, null when not computable
     */
    public function __construct(
        public string $amount,
        public array $materials,
        public string $estimatedCost,
        public ?string $hours,
    ) {
    }
}
