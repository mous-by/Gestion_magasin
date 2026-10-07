<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fournisseurs (cahier §10, §24). `admin_references` : références administratives si nécessaires (NIF, RCCM…).
     * L'historique des livraisons est celui de ses entrées (phase 4).
     */
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('admin_references')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
