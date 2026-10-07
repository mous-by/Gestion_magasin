<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteurs de numérotation (cahier §19, RG10) : un par préfixe (ENT, SOR, REG, INV) et par année, lu et
     * incrémenté sous verrou (`lockForUpdate`) dans la transaction qui valide le document (CLAUDE.md D-M5).
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
