<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unités de mesure (cahier §9 : « U, paquet, rouleau, mètre, etc. », §24). `allows_decimal` : une quantité
     * fractionnaire est admise (mètre, litre…) — PROPOSITION TECHNIQUE, utilisée par les entrées et sorties.
     */
    public function up(): void
    {
        Schema::create('unites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('symbol', 15)->unique();
            $table->boolean('allows_decimal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites');
    }
};
