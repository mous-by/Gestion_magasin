<?php

namespace App\Support;

/**
 * Affichage des quantités (decimal(12,3) en base) : sans zéros inutiles, virgule décimale, espace des milliers.
 * « 80.000 » → « 80 », « 2.500 » → « 2,5 », « 1250.000 » → « 1 250 ». Réutilisé par les entrées, sorties et états.
 */
class Quantity
{
    public static function format(string|int|float|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $formatted = number_format((float) $value, 3, ',', ' ');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
