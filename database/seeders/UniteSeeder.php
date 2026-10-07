<?php

namespace Database\Seeders;

use App\Models\Unite;
use Illuminate\Database\Seeder;

/**
 * Unités citées par le cahier (§9 : « U, paquet, rouleau, mètre, etc. »). Idempotent : une unité existante (même
 * modifiée ou supprimée) n'est jamais recréée ni écrasée. Liste complète À VALIDER (cahier §33).
 */
class UniteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Unité', 'symbol' => 'U', 'allows_decimal' => false],
            ['name' => 'Paquet', 'symbol' => 'pqt', 'allows_decimal' => false],
            ['name' => 'Rouleau', 'symbol' => 'rlx', 'allows_decimal' => false],
            ['name' => 'Mètre', 'symbol' => 'm', 'allows_decimal' => true],
        ] as $unite) {
            if (! Unite::withTrashed()->where('name', $unite['name'])->orWhere('symbol', $unite['symbol'])->exists()) {
                Unite::create($unite);
            }
        }
    }
}
