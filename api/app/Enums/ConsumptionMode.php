<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Consumption rule of a suggested material (domain.md §7).
 */
enum ConsumptionMode: string
{
    case Coverage = 'coverage';
    case PerUnit = 'per_unit';
    case None = 'none';
}
