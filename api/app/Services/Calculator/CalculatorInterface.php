<?php declare(strict_types=1);

namespace App\Services\Calculator;

/**
 * The only "per trade" point of the code (stack.md): the tenant trade selects the implementation.
 */
interface CalculatorInterface
{
    /**
     * Amount, material requirement and cost of a line.
     *
     * @param LineInput           $line
     * @param CalculationSettings $settings
     *
     * @return LineResult
     */
    public function line(LineInput $line, CalculationSettings $settings): LineResult;

    /**
     * Totals, margin and hours of a quote from its calculated lines.
     *
     * @param array<LineResult> $lines
     * @param float|int|string  $vatRate e.g. 0 (forfettario) or 22
     *
     * @return QuoteTotals
     */
    public function totals(array $lines, float|int|string $vatRate): QuoteTotals;
}
