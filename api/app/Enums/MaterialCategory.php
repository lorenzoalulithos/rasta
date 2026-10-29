<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Category of a material (domain.md §3.5).
 */
enum MaterialCategory: string
{
    case PaintInterior = 'paint_interior';
    case PaintExterior = 'paint_exterior';
    case Enamel = 'enamel';
    case Wood = 'wood';
    case Filler = 'filler';
    case Primer = 'primer';
    case Solvent = 'solvent';
    case Consumable = 'consumable';
    case Tool = 'tool';
    case Other = 'other';
}
