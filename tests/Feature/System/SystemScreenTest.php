<?php

namespace Tests\Feature\System;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * page Système réservée au superadmin, état en lecture seule et maintenance sur liste blanche, journalisée.
 */
class SystemScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->superadmin = $this->superadmin();
    }

    private function failedJob(string $exception, string $payload = '{"displayName":"App\\\\Jobs\\\\Test"}'): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    public function test_the_screen_is_reserved_to_the_superadmin(): void
    {
        $this->get('/system')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrateur()->create())->get('/system')->assertForbidden();
        $this->actingAs(User::factory()->magasinier()->create())->get('/system')->assertForbidden();
        $this->actingAs($this->superadmin)->get('/system')->assertOk()->assertSee('ENVIRONNEMENT', false);
    }

    public function test_it_reports_the_installation_state(): void
    {
        $this->actingAs($this->superadmin)->get('/system')
            ->assertOk()
            ->assertSee(PHP_VERSION)
            ->assertSee(app()->version())
            ->assertSee('Connectée')
            ->assertViewHas('database', fn (array $db) => $db['ok'] === true && $db['tables'] > 10 && $db['migrations'] > 0)
            ->assertViewHas('environment', fn (array $env) => $env['env'] === 'testing');
    }

    public function test_no_secret_leaves_the_page(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/system')->assertOk();

        $response->assertDontSee(config('app.key'), false);
        foreach ([config('database.connections.mysql.password'), config('database.connections.mysql.username')] as $secret) {
            if (is_string($secret) && strlen($secret) >= 4) {
                $response->assertDontSee($secret, false);
            }
        }
    }

    public function test_it_follows_the_theme_template(): void
    {
        $this->actingAs($this->superadmin)->get('/system')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('js-maintenance', false);
    }

    public function test_failed_jobs_show_the_first_line_of_the_error_only(): void
    {
        $this->failedJob("RuntimeException: échec d'envoi\n#0 /var/www/vendor/x.php(12): secret-stack-frame()", '{"secret":"SECRET-PAYLOAD"}');

        $this->actingAs($this->superadmin)->get('/system')
            ->assertSee("RuntimeException: échec d'envoi")
            ->assertDontSee('secret-stack-frame')
            ->assertDontSee('SECRET-PAYLOAD')
            ->assertViewHas('queue', fn (array $queue) => $queue['failed_total'] === 1 && count($queue['failed']) === 1);
    }

    public function test_only_the_last_ten_failed_jobs_are_listed(): void
    {
        foreach (range(1, 12) as $i) {
            $this->failedJob("Erreur numéro {$i}");
        }

        $this->actingAs($this->superadmin)->get('/system')
            ->assertViewHas('queue', fn (array $queue) => $queue['failed_total'] === 12 && count($queue['failed']) === 10)
            ->assertSee('Erreur numéro 12')
            ->assertDontSee('Erreur numéro 2<', false);
    }

    public function test_pending_jobs_are_counted_for_the_database_queue(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);

        $this->actingAs($this->superadmin)->get('/system')
            ->assertViewHas('queue', fn (array $queue) => $queue['pending'] === 1);
    }

    public function test_a_maintenance_action_runs_and_is_audited(): void
    {
        Cache::put('sonde', 'présente');

        $this->actingAs($this->superadmin)->post('/system/maintenance/cache')
            ->assertRedirect(route('system.index'))
            ->assertSessionHas('status');

        $this->assertNull(Cache::get('sonde'));
        $log = ActivityLog::where('action', 'system.cache_cleared')->sole();
        $this->assertSame($this->superadmin->id, $log->user_id);
        $this->assertSame('superadmin', $log->user_role);
        $this->assertSame('system', $log->module);

        $this->actingAs($this->superadmin)->post('/system/maintenance/views')->assertRedirect(route('system.index'));
        $this->assertTrue(ActivityLog::where('action', 'system.views_cleared')->exists());
    }

    public function test_only_whitelisted_actions_can_be_run(): void
    {
        $before = ActivityLog::count();

        foreach (['migrate', 'db', 'optimize', 'inconnue'] as $task) {
            $this->actingAs($this->superadmin)->post("/system/maintenance/{$task}")->assertNotFound();
        }

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_maintenance_is_forbidden_to_everyone_but_the_superadmin(): void
    {
        Cache::put('sonde', 'présente');

        $this->post('/system/maintenance/cache')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrateur()->create())->post('/system/maintenance/cache')->assertForbidden();
        $this->actingAs(User::factory()->magasinier()->create())->post('/system/maintenance/cache')->assertForbidden();

        $this->assertSame('présente', Cache::get('sonde'));
        $this->assertFalse(ActivityLog::where('action', 'system.cache_cleared')->exists());
    }

    public function test_consulting_the_page_writes_nothing_to_the_audit(): void
    {
        $before = ActivityLog::count();

        $this->actingAs($this->superadmin)->get('/system')->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }
}
