<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Unit a job is sold in (domain.md §3.6).
 */
enum JobUnit: string
{
    case SquareMeter = 'sqm';
    case Hour = 'hour';
    case Piece = 'pc';
    case Pair = 'pair';
    case LinearMeter = 'lm';
}
