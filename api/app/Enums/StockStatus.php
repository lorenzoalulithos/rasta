<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Colour of a material in the stock list (flows.md §3.1): red, yellow or normal.
 */
enum StockStatus: string
{
    /** Available below zero: committed more than there is. */
    case Negative = 'negative';

    /** Available below the minimum stock. */
    case Low = 'low';

    case Ok = 'ok';
}
