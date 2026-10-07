<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock en cache (CLAUDE.md D-M3) : mis à jour dans la même transaction que chaque mouvement, jamais saisi.
     * La vérité reste la somme des mouvements validés (cahier §15, Annexe B, RG09).
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->decimal('stock_quantity', 12, 3)->default(0)->after('min_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
