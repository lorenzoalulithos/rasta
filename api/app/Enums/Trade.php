<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Trade of the tenant: selects the calculator (domain.md §3.1).
 */
enum Trade: string
{
    case Painter = 'painter';
}
