<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Role of a suggested material in a job (domain.md §3.7).
 */
enum JobMaterialRole: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Checklist = 'checklist';
}
