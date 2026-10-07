<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Le schéma correspond exactement au dictionnaire de données de CLAUDE.md (§4.1) : toute table inattendue fait
 * échouer ce test, qui sert de garde-fou contre une dérive de schéma. Chaque module ajoute ici ses tables (cahier
 * §24). Ces tests tournent sur MariaDB (db_gestion_magasin_test) : les clés y sont réellement appliquées.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_foundation_has_exactly_the_validated_tables(): void
    {
        $tables = collect(Schema::getTables(schema: DB::getDatabaseName()))->pluck('name')->reject(fn ($name) => $name === 'migrations')->sort()->values()->all();

        $expected = [
            'app_settings', 'articles', 'audit_logs', 'cache', 'cache_locks', 'categories', 'document_sequences', 'entree_details', 'entrees',
            'failed_jobs', 'fournisseurs', 'job_batches', 'jobs', 'model_has_permissions', 'model_has_roles', 'mouvements_stock',
            'permissions', 'regularisations', 'role_has_permissions', 'roles', 'services', 'sessions', 'sortie_details', 'sorties',
            'unites', 'users',
        ];

        $this->assertSame($expected, $tables);
    }

    public function test_users_have_no_email_and_there_is_no_password_reset_table(): void
    {
        foreach (['email', 'email_verified_at', 'photo', 'username'] as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column), "users.$column ne doit pas exister (D5)");
        }
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_users_table_has_the_validated_columns(): void
    {
        $expected = [
            'id', 'name', 'phone', 'password', 'is_active', 'must_change_password', 'password_changed_at',
            'last_login_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
        ];

        $this->assertSame($expected, Schema::getColumnListing('users'));
    }

    public function test_phone_is_the_required_unique_login_identifier(): void
    {
        $this->insertUser('a', ['phone' => '+22370000001']);
        $this->insertUser('b'); // numéro généré, distinct

        try {
            $this->insertUser('c', ['phone' => '+22370000001']);
            $this->fail('unicité du numéro');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'sans numéro', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]); // NOT NULL
    }

    public function test_a_model_can_only_have_one_role(): void
    {
        $now = now();
        $roleA = DB::table('roles')->insertGetId(['name' => 'magasinier', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $roleB = DB::table('roles')->insertGetId(['name' => 'administrateur', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $user = $this->insertUser('x');

        DB::table('model_has_roles')->insert(['role_id' => $roleA, 'model_type' => 'App\Models\User', 'model_id' => $user]);

        $this->expectException(QueryException::class);
        DB::table('model_has_roles')->insert(['role_id' => $roleB, 'model_type' => 'App\Models\User', 'model_id' => $user]);
    }

    public function test_permissions_are_custom_by_default(): void
    {
        $now = now();
        $id = DB::table('permissions')->insertGetId(['name' => 'demo.view', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        $this->assertSame('custom', DB::table('permissions')->where('id', $id)->value('source'));
    }

    public function test_audit_logs_are_append_only_by_design_and_reference_their_author(): void
    {
        $this->assertTrue(Schema::hasColumn('audit_logs', 'created_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'deleted_at'));

        $user = $this->insertUser('auteur');
        $id = DB::table('audit_logs')->insertGetId([
            'user_id' => $user, 'action' => 'auth.login', 'module' => 'auth', 'description' => 'Connexion', 'channel' => 'web',
        ]);
        $this->assertNotNull(DB::table('audit_logs')->where('id', $id)->value('created_at'));

        // Un utilisateur qui a un historique d'audit ne peut pas être supprimé physiquement.
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user)->delete();
    }

    private int $phoneSequence = 100;

    private function insertUser(string $name, array $attributes = []): int
    {
        return DB::table('users')->insertGetId(array_merge([
            'name' => $name, 'phone' => '+223700'.str_pad((string) $this->phoneSequence++, 5, '0', STR_PAD_LEFT),
            'password' => 'not-a-real-hash', 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }
}
