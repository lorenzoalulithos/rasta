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

    /**
     * Italian label for messages.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'in bozza',
            self::Sent => 'inviato',
            self::Accepted => 'accettato',
            self::Rejected => 'rifiutato',
            self::Completed => 'chiuso',
        };
    }

    /**
     * Draft and sent quotes can still be edited (sent goes back to draft).
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        return self::Draft === $this || self::Sent === $this;
    }
}
