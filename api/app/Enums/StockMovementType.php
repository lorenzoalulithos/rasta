<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Type of a stock movement (domain.md §3.12).
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';
}
