<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entrées / réceptions (cahier §11, §24). Saisie en brouillon (sans effet sur le stock), puis validée : numéro
     * ENT-AAAA-NNNN attribué, stock mis à jour (CLAUDE.md D-M1). Une entrée validée ne se modifie plus : elle
     * s'annule par contre-mouvement, motif obligatoire (RG05). Seuls les brouillons se suppriment (soft delete).
     */
    public function up(): void
    {
        Schema::create('entrees', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->date('received_on');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->restrictOnDelete();
            $table->string('delivery_note_number', 60)->nullable();
            $table->string('order_reference', 60)->nullable();
            $table->text('observation')->nullable();
            // varchar validé par l'enum DocumentStatus (brouillon, validee, annulee), pas d'enum SQL.
            $table->string('status', 20)->default('brouillon')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('received_on');
        });

        Schema::create('entree_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entree_id')->constrained('entrees')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->decimal('expected_qty', 12, 3)->nullable();
            $table->decimal('delivered_qty', 12, 3);
            $table->decimal('accepted_qty', 12, 3);
            // Livrée − acceptée : endommagée, non conforme… jamais en stock (cahier §12).
            $table->decimal('rejected_qty', 12, 3)->default(0);
            $table->string('rejection_reason', 30)->nullable();
            $table->string('observation', 500)->nullable();
            $table->timestamps();

            $table->unique(['entree_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entree_details');
        Schema::dropIfExists('entrees');
    }
};
