<?php

namespace App\Enums;

/**
 * Cycle d'une entrée ou d'une sortie (CLAUDE.md D-M1) : brouillon (modifiable, sans effet sur le stock) → validé
 * (numéro, stock) → annulé (contre-mouvement tracé, motif obligatoire).
 */
enum DocumentStatus: string
{
    case Brouillon = 'brouillon';
    case Validee = 'validee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-secondary-subtle',
            self::Validee => 'bg-success-subtle',
            self::Annulee => 'bg-danger-subtle',
        };
    }
}
