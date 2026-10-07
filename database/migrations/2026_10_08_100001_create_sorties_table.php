<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sorties (cahier §13, §14, §24). Même cycle que les entrées (CLAUDE.md D-M1) : brouillon → validée (numéro
     * SOR-AAAA-NNNN, stock diminué, refus si le stock est insuffisant, RG03, RG04) → annulée (contre-mouvement).
     * `stock_before` fige, à la validation, le stock disponible de chaque ligne (preuve du contrôle, bon de sortie).
     */
    public function up(): void
    {
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->date('issued_on');
            $table->string('requester_name', 150);
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('reason');
            $table->string('paper_reference', 60)->nullable();
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

            $table->index('issued_on');
        });

        Schema::create('sortie_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sortie_id')->constrained('sorties')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3)->nullable();
            $table->string('observation', 500)->nullable();
            $table->timestamps();

            $table->unique(['sortie_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sortie_details');
        Schema::dropIfExists('sorties');
    }
};
