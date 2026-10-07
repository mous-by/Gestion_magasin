<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi des écarts de réception (cahier §15.1 « écart de réception non traité ») : `has_discrepancy` est fixé à la
     * validation ; l'écart reste « à traiter » tant que la suite donnée (complément livré, avoir, retour…) n'est pas
     * enregistrée.
     */
    public function up(): void
    {
        Schema::table('entrees', function (Blueprint $table) {
            $table->boolean('has_discrepancy')->default(false)->after('status');
            $table->text('discrepancy_resolution')->nullable()->after('has_discrepancy');
            $table->dateTime('discrepancy_resolved_at')->nullable()->after('discrepancy_resolution');
            $table->foreignId('discrepancy_resolved_by')->nullable()->after('discrepancy_resolved_at')->constrained('users')->restrictOnDelete();

            $table->index(['has_discrepancy', 'discrepancy_resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('entrees', function (Blueprint $table) {
            $table->dropIndex(['has_discrepancy', 'discrepancy_resolved_at']);
            $table->dropConstrainedForeignId('discrepancy_resolved_by');
            $table->dropColumn(['has_discrepancy', 'discrepancy_resolution', 'discrepancy_resolved_at']);
        });
    }
};
