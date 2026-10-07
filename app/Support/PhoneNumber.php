<?php

namespace App\Support;

/**
 * Numéro de téléphone = identifiant de connexion (D26). Stocké en E.164 (`+223XXXXXXXX`).
 * Accepte la saisie courante : « 70 00 00 01 », « +223 70 00 00 01 », « 0022370000001 », « 223 70 00 00 01 ».
 * L'indicatif par défaut (Mali, 223) s'applique aux numéros locaux de 8 chiffres.
 */
class PhoneNumber
{
    public const E164_PATTERN = '/^\+[1-9]\d{7,14}$/';

    /**
     * Forme E.164, ou null si la saisie n'est pas un numéro exploitable.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $number = preg_replace('/[\s.\-()]/', '', trim($input));

        if ($number === '' || $number === null) {
            return null;
        }

        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }

        if (! str_starts_with($number, '+')) {
            $country = (string) config('authorization.default_country_code', '223');

            if (preg_match('/^\d{8}$/', $number)) {
                $number = '+'.$country.$number;
            } elseif (str_starts_with($number, $country) && preg_match('/^\d+$/', $number) && strlen($number) === strlen($country) + 8) {
                $number = '+'.$number;
            } else {
                return null;
            }
        }

        return preg_match(self::E164_PATTERN, $number) ? $number : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }
}
