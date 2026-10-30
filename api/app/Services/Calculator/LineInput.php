<?php declare(strict_types=1);

namespace App\Services\Calculator;

/**
 * A quote line to calculate (domain.md §3.9): plain values, so the same input
 * serves saved lines and the live preview.
 */
final readonly class LineInput
{
    /**
     * @var numeric-string
     */
    public string $quantity;

    /**
     * @var numeric-string
     */
    public string $unitPrice;

    /**
     * @var numeric-string|null
     */
    public ?string $estimatedHoursPerUnit;

    /**
     * @param float|int|string      $quantity              e.g. 181 m²
     * @param float|int|string      $unitPrice             price to the customer per unit, VAT excluded
     * @param int|null              $coats                 coats of the line, null for jobs without coats
     * @param array<MaterialInput>  $materials             materials of the line
     * @param bool                  $isHourly              unit `hour`: hours = quantity (domain.md §7.4)
     * @param float|int|string|null $estimatedHoursPerUnit hours per unit of the job, optional
     */
    public function __construct(
        float|int|string $quantity,
        float|int|string $unitPrice,
        public ?int $coats = null,
        public array $materials = [],
        public bool $isHourly = false,
        float|int|string|null $estimatedHoursPerUnit = null,
    ) {
        $this->quantity = Decimal::of($quantity);
        $this->unitPrice = Decimal::of($unitPrice);
        $this->estimatedHoursPerUnit = Decimal::nullable($estimatedHoursPerUnit);
    }
}
