<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Rounding of required packages (domain.md §5.5).
 */
enum PackageRounding: string
{
    case Up = 'up';
    case Fractional = 'fractional';
}
