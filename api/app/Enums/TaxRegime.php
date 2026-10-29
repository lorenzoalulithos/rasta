<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Tax regime of the tenant (domain.md §3.1).
 */
enum TaxRegime: string
{
    case Forfettario = 'forfettario';
    case Ordinario = 'ordinario';
}
