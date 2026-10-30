<?php declare(strict_types=1);

namespace App\Services\Calculator;

/**
 * Totals of a quote (domain.md §3.8). Margin and hours are internal: never on the PDF.
 */
final readonly class QuoteTotals
{
    /**
     * @param numeric-string      $subtotal              sum of line amounts
     * @param numeric-string      $vatAmount             subtotal × VAT rate
     * @param numeric-string      $total                 subtotal + VAT
     * @param numeric-string      $estimatedCost         sum of line material costs
     * @param numeric-string      $estimatedMargin       subtotal − estimated cost
     * @param numeric-string|null $estimatedMarginPct    margin on subtotal in %, 1 decimal; null on an empty quote
     * @param numeric-string|null $estimatedHours        sum of line hours, null when no line has them
     */
    public function __construct(
        public string $subtotal,
        public string $vatAmount,
        public string $total,
        public string $estimatedCost,
        public string $estimatedMargin,
        public ?string $estimatedMarginPct,
        public ?string $estimatedHours,
    ) {
    }
}
