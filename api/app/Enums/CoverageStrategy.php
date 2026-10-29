<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Which coverage the calculator uses (domain.md §3.5).
 */
enum CoverageStrategy: string
{
    case Min = 'min';
    case Avg = 'avg';
    case Max = 'max';
}
