<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Unit a material is consumed in (domain.md §3.5).
 */
enum BaseUnit: string
{
    case Liter = 'lt';
    case Kilogram = 'kg';
    case Meter = 'm';
    case Piece = 'pc';
}
