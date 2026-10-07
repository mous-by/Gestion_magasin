<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Journal d'audit (cahier §16, §21, RG12 ; table `audit_logs`, cahier §24) : append-only. Pas de updated_at ni de soft delete ; le modèle refuse update et
     * delete, et en production le compte applicatif n'a pas les droits UPDATE/DELETE sur cette table.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('user_role', 50)->nullable();
            $table->string('action', 100);
            $table->string('module', 50);
            // text : une description longue ne doit jamais faire échouer un audit synchrone.
            $table->text('description');
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            // varchar validé par l'enum ActivityChannel (web, console), pas d'enum SQL.
            $table->string('channel', 20);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // DATETIME et non TIMESTAMP : pas d'ON UPDATE implicite (explicit_defaults_for_timestamp = 0 sur MariaDB 10.4), pas de limite 2038.
            $table->dateTime('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
