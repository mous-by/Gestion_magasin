<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue des articles (cahier §9, §24). Référence unique, y compris parmi les articles supprimés (RG01) ;
     * un article inactif ne sert plus dans une nouvelle opération (RG07). Le stock n'est pas ici : il se calcule à
     * partir des mouvements (phase 6, CLAUDE.md D-M3).
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('designation', 200)->index();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unite_id')->constrained('unites')->restrictOnDelete();
            $table->string('brand_model', 150)->nullable();
            $table->decimal('min_threshold', 12, 3)->nullable();
            $table->string('location', 100)->nullable();
            $table->text('observation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
