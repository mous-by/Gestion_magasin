<?php

namespace App\Enums;

/**
 * Nature d'un mouvement de stock (cahier §15, §19). Le signe de la quantité porte le sens (+ entrée, − sortie).
 */
enum MouvementType: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';
    case Regularisation = 'regularisation';
    case Annulation = 'annulation';

    public function label(): string
    {
        return match ($this) {
            self::Entree => 'Réception',
            self::Sortie => 'Sortie',
            self::Regularisation => 'Régularisation',
            self::Annulation => 'Annulation',
        };
    }
}
