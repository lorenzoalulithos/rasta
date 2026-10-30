<?php declare(strict_types=1);

namespace App\Services\Calculator;

use App\Enums\ConsumptionMode;
use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;

/**
 * Painter calculator, domain.md §7.
 *
 *   coverage      = coverage by strategy (min, avg, max; the only one present otherwise)
 *   coats         = line coats if apply_coats, else 1
 *   required_qty  = quantity × coats / coverage            (coverage)
 *                 = quantity × consumption_per_unit × coats (per_unit)
 *   packages_raw  = required_qty / package_size
 *   required_pkgs = ceil(packages_raw) if rounding up, packages_raw otherwise
 *   cost          = required_pkgs × cost_price
 *
 * Intermediate values keep 10 decimals; quantities and packages are rounded to 3, money to 2,
 * only at the end (domain.md §7.1). Example of §7.3: 181 m², 2 coats, Tempera 8/10 → 40.222 lt,
 * 3 bidoni, 143.07 €.
 */
final class PainterCalculator implements CalculatorInterface
{
    /**
     * Decimals of the margin percentage.
     *
     * @var int
     */
    private const PERCENT_DECIMALS = 1;

    /**
     * {@inheritdoc}
     */
    public function line(LineInput $line, CalculationSettings $settings): LineResult
    {
        $materials = [];
        $cost = '0';

        foreach ($line->materials as $material) {
            $result = $this->material($line, $material, $settings);
            $materials[] = $result;

            if ($material->selected) {
                $cost = bcadd($cost, $result->estimatedCost, Decimal::MONEY);
            }
        }

        return new LineResult(
            amount: Decimal::round(bcmul($line->quantity, $line->unitPrice, Decimal::WORKING_SCALE), Decimal::MONEY),
            materials: $materials,
            estimatedCost: Decimal::round($cost, Decimal::MONEY),
            hours: $this->hours($line),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function totals(array $lines, float|int|string $vatRate): QuoteTotals
    {
        $subtotal = '0';
        $cost = '0';
        $hours = null;

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line->amount, Decimal::MONEY);
            $cost = bcadd($cost, $line->estimatedCost, Decimal::MONEY);

            if (null !== $line->hours) {
                $hours = bcadd($hours ?? '0', $line->hours, Decimal::QUANTITY);
            }
        }

        $vat = $this->vatAmount($subtotal, $vatRate);
        $margin = bcsub($subtotal, $cost, Decimal::MONEY);

        return new QuoteTotals(
            subtotal: Decimal::round($subtotal, Decimal::MONEY),
            vatAmount: $vat,
            total: Decimal::round(bcadd($subtotal, $vat, Decimal::MONEY), Decimal::MONEY),
            estimatedCost: Decimal::round($cost, Decimal::MONEY),
            estimatedMargin: Decimal::round($margin, Decimal::MONEY),
            estimatedMarginPct: Decimal::isPositive($subtotal)
                ? Decimal::round(bcdiv(bcmul($margin, '100', Decimal::WORKING_SCALE), $subtotal, Decimal::WORKING_SCALE), self::PERCENT_DECIMALS)
                : null,
            estimatedHours: $hours,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function vatAmount(string $subtotal, float|int|string $vatRate): string
    {
        return Decimal::round(
            bcdiv(bcmul($subtotal, Decimal::of($vatRate), Decimal::WORKING_SCALE), '100', Decimal::WORKING_SCALE),
            Decimal::MONEY,
        );
    }

    /**
     * @param LineInput           $line
     * @param MaterialInput       $material
     * @param CalculationSettings $settings
     *
     * @return MaterialResult
     */
    private function material(LineInput $line, MaterialInput $material, CalculationSettings $settings): MaterialResult
    {
        $coats = $material->applyCoats && null !== $line->coats ? (string) $line->coats : '1';
        $coverage = ConsumptionMode::Coverage === $material->consumptionMode
            ? $this->coverage($material, $settings->coverageStrategy)
            : null;

        $required = match ($material->consumptionMode) {
            ConsumptionMode::Coverage => null === $coverage
                ? '0'
                : bcdiv(bcmul($line->quantity, $coats, Decimal::WORKING_SCALE), $coverage, Decimal::WORKING_SCALE),
            ConsumptionMode::PerUnit => bcmul(
                bcmul($line->quantity, $material->consumptionPerUnit ?? '0', Decimal::WORKING_SCALE),
                $coats,
                Decimal::WORKING_SCALE,
            ),
            ConsumptionMode::None => '0',
        };

        $packagesRaw = Decimal::isPositive($material->packageSize)
            ? bcdiv($required, $material->packageSize, Decimal::WORKING_SCALE)
            : '0';

        $packages = PackageRounding::Up === $settings->packageRounding
            ? Decimal::ceil($packagesRaw, Decimal::QUANTITY)
            : Decimal::round($packagesRaw, Decimal::QUANTITY);

        $unitCost = Decimal::round($material->costPrice ?? '0', Decimal::MONEY);

        return new MaterialResult(
            input: $material,
            coverageUsed: null === $coverage ? null : Decimal::round($coverage, Decimal::MONEY),
            requiredQuantity: Decimal::round($required, Decimal::QUANTITY),
            requiredPackages: $packages,
            unitCost: $unitCost,
            // Stored packages × cost: the snapshot gives back the same cost (domain.md §3.10)
            estimatedCost: Decimal::round(bcmul($packages, $unitCost, Decimal::WORKING_SCALE), Decimal::MONEY),
        );
    }

    /**
     * Coverage by strategy; the only value present when one is missing; null without coverage.
     *
     * @param MaterialInput    $material
     * @param CoverageStrategy $strategy
     *
     * @return numeric-string|null
     */
    private function coverage(MaterialInput $material, CoverageStrategy $strategy): ?string
    {
        $min = $material->coverageMin;
        $max = $material->coverageMax;

        if (null === $min || null === $max) {
            $only = $min ?? $max;

            return null !== $only && Decimal::isPositive($only) ? $only : null;
        }

        $coverage = match ($strategy) {
            CoverageStrategy::Min => $min,
            CoverageStrategy::Max => $max,
            CoverageStrategy::Avg => bcdiv(bcadd($min, $max, Decimal::WORKING_SCALE), '2', Decimal::WORKING_SCALE),
        };

        return Decimal::isPositive($coverage) ? $coverage : null;
    }

    /**
     * Hours of a line (domain.md §7.4): quantity for hourly lines, quantity × hours per unit otherwise.
     *
     * @param LineInput $line
     *
     * @return numeric-string|null
     */
    private function hours(LineInput $line): ?string
    {
        if ($line->isHourly) {
            return Decimal::round($line->quantity, Decimal::QUANTITY);
        }

        if (null === $line->estimatedHoursPerUnit) {
            return null;
        }

        return Decimal::round(bcmul($line->quantity, $line->estimatedHoursPerUnit, Decimal::WORKING_SCALE), Decimal::QUANTITY);
    }
}
