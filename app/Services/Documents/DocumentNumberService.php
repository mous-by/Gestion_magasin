<?php

namespace App\Services\Documents;

use App\Models\DocumentSequence;
use App\Support\DocumentSettings;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numérotation des documents (cahier §19, RG10, CLAUDE.md D-M5) : format réglé dans Paramètres → Documents (par
 * défaut config/documents.php : PRÉFIXE-AAAA-NNNN), remise à 1 chaque année.
 * À appeler DANS la transaction qui valide le document : la ligne du compteur est verrouillée jusqu'à la fin de
 * la transaction, deux validations simultanées ne peuvent donc pas recevoir le même numéro.
 */
class DocumentNumberService
{
    public function __construct(private readonly DocumentSettings $settings) {}

    public function next(string $prefix, ?int $year = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La numérotation doit être appelée dans une transaction.');
        }

        $year ??= (int) now()->format('Y');

        DocumentSequence::query()->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $sequence = DocumentSequence::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->firstOrFail();
        $sequence->last_number++;
        $sequence->save();

        return strtr($this->settings->numberFormat(), [
            '{prefix}' => $prefix,
            '{year}' => (string) $year,
            '{number}' => str_pad((string) $sequence->last_number, $this->settings->numberPadding(), '0', STR_PAD_LEFT),
        ]);
    }
}
