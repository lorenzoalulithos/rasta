<?php declare(strict_types=1);

namespace App\Services\Calculator;

use App\Enums\ConsumptionMode;

/**
 * A material of a line with its consumption rule (domain.md §3.5, §3.10, §7).
 */
final readonly class MaterialInput
{
    /**
     * @var numeric-string|null
     */
    public ?string $coverageMin;

    /**
     * @var numeric-string|null
     */
    public ?string $coverageMax;

    /**
     * @var numeric-string
     */
    public string $packageSize;

    /**
     * @var numeric-string|null
     */
    public ?string $costPrice;

    /**
     * @var numeric-string|null
     */
    public ?string $consumptionPerUnit;

    /**
     * @param ConsumptionMode       $consumptionMode    coverage, per_unit or none
     * @param float|int|string      $packageSize        content of a package in base unit, e.g. 14 lt
     * @param float|int|string|null $costPrice          price per package, VAT included
     * @param float|int|string|null $coverageMin        m² per base unit, e.g. 8
     * @param float|int|string|null $coverageMax        m² per base unit, e.g. 10
     * @param float|int|string|null $consumptionPerUnit base unit per job unit, only with per_unit
     * @param bool                  $applyCoats         multiply by the coats of the line
     * @param bool                  $selected           ticked by the user: only selected materials cost
     * @param string|null           $key                caller's reference, e.g. the material id
     */
    public function __construct(
        public ConsumptionMode $consumptionMode,
        float|int|string $packageSize,
        float|int|string|null $costPrice,
        float|int|string|null $coverageMin = null,
        float|int|string|null $coverageMax = null,
        float|int|string|null $consumptionPerUnit = null,
        public bool $applyCoats = false,
        public bool $selected = true,
        public ?string $key = null,
    ) {
        $this->packageSize = Decimal::of($packageSize);
        $this->costPrice = Decimal::nullable($costPrice);
        $this->coverageMin = Decimal::nullable($coverageMin);
        $this->coverageMax = Decimal::nullable($coverageMax);
        $this->consumptionPerUnit = Decimal::nullable($consumptionPerUnit);
    }
}
