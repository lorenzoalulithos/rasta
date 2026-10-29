<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a quote (domain.md §6).
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
