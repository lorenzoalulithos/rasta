<?php declare(strict_types=1);

namespace App\Enums;

/**
 * How a material is used (domain.md §3.5).
 */
enum MaterialKind: string
{
    case ConsumableQuantity = 'consumable_quantity';
    case ConsumableChecklist = 'consumable_checklist';
    case Tool = 'tool';
}
