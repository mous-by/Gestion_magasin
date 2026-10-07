<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mouvements de stock (cahier §15, §16, §24) : journal append-only, jamais modifié ni supprimé (RG05). Chaque
     * mouvement a une date, une référence de document et un utilisateur (RG06). Stock d'un article = somme de
     * `quantity` (signée) (Annexe B, RG09) ; `balance_after` fige le solde au moment de l'écriture (fiche de stock).
     */
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            // varchar validé par l'enum MouvementType (entree, sortie, regularisation, annulation).
            $table->string('type', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->string('reference', 40);
            $table->date('moved_on');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['article_id', 'id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'moved_on']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
