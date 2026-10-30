<?php declare(strict_types=1);

namespace App\Services\Calculator;

use App\Enums\Trade;
use App\Models\Database\v1\Tenant;

/**
 * Picks the calculator of the tenant trade (stack.md). Only painters in v1.
 */
final class CalculatorResolver
{
    /**
     * @param PainterCalculator $painter
     */
    public function __construct(private readonly PainterCalculator $painter)
    {
    }

    /**
     * @param Tenant $tenant
     *
     * @return CalculatorInterface
     */
    public function for(Tenant $tenant): CalculatorInterface
    {
        return match ($tenant->trade) {
            Trade::Painter => $this->painter,
        };
    }
}
