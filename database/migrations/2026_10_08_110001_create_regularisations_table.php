<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Régularisations de stock (cahier §16, §19, Annexe B, RG11) : correction justifiée d'un stock constaté
     * différent du stock calculé (casse, perte, erreur de comptage…). Numéro REG-AAAA-NNNN, motif obligatoire,
     * ancienne et nouvelle valeur. Non modifiable : une erreur se corrige par une nouvelle régularisation.
     */
    public function up(): void
    {
        Schema::create('regularisations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('regularized_on');
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->decimal('old_balance', 12, 3);
            $table->decimal('new_balance', 12, 3);
            $table->decimal('quantity', 12, 3);
            $table->string('reason');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularisations');
    }
};
