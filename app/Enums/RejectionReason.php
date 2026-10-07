<?php

namespace App\Enums;

/**
 * Motif d'une quantité livrée mais non acceptée (cahier §12) : elle n'entre jamais en stock (CLAUDE.md D-M4).
 */
enum RejectionReason: string
{
    case Endommage = 'endommage';
    case NonConforme = 'non_conforme';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Endommage => 'Endommagé',
            self::NonConforme => 'Non conforme',
            self::Autre => 'Autre motif',
        };
    }
}
