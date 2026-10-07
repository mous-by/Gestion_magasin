<?php

namespace Tests\Feature\Database;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Permissions\PermissionSynchronizer;
use App\Services\Users\SuperadminBootstrapper;
use App\Services\Users\UserProvisioningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * D27 : les superadmins de .env sont créés automatiquement s'ils n'existent pas, en développement comme hébergé.
 */
class SuperadminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $environment = 'local', string $password = 'Fixture-Test-1'): void
    {
        $this->app['env'] = $environment;
        config([
            'superadmins.auto_create' => true,
            'superadmins.password' => $password,
            'superadmins.accounts' => [
                ['name' => 'Moustapha BARRY', 'phone' => '+22370000001'],
                ['name' => 'Amadou KAREMBE', 'phone' => '70 00 00 02'],
            ],
        ]);
    }

    private function bootstrapper(): SuperadminBootstrapper
    {
        return $this->app->make(SuperadminBootstrapper::class);
    }

    #[DataProvider('environments')]
    public function test_both_superadmins_are_created_automatically_in_every_environment(string $environment): void
    {
        $this->configure($environment);

        $created = $this->bootstrapper()->ensure();

        $this->assertSame(['+22370000001', '+22370000002'], $created);
        $this->assertSame(2, User::count());
        foreach (['+22370000001' => 'Moustapha BARRY', '+22370000002' => 'Amadou KAREMBE'] as $phone => $name) {
            $user = User::where('phone', $phone)->firstOrFail();
            $this->assertSame($name, $user->name);
            $this->assertTrue($user->isSuperadmin());
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check('Fixture-Test-1', $user->password));
            $this->assertNotSame('Fixture-Test-1', $user->getRawOriginal('password'));
        }
    }

    public static function environments(): array
    {
        return ['local' => ['local'], 'production' => ['production'], 'staging' => ['staging']];
    }

    public function test_outside_local_the_password_is_temporary_and_must_be_changed_at_first_login(): void
    {
        $this->configure('production');
        $this->bootstrapper()->ensure();
        $this->assertSame(2, User::where('must_change_password', true)->count());
    }

    public function test_in_local_the_password_is_kept_so_a_fresh_seed_stays_practical(): void
    {
        $this->configure('local');
        $this->bootstrapper()->ensure();
        $this->assertSame(0, User::where('must_change_password', true)->count());
    }

    public function test_it_is_idempotent_and_only_creates_what_is_missing(): void
    {
        $this->configure();
        $this->bootstrapper()->ensure();
        $this->assertSame([], $this->app->make(SuperadminBootstrapper::class)->ensure());
        $this->assertSame(2, User::count());

        User::where('phone', '+22370000002')->forceDelete();
        $fresh = new SuperadminBootstrapper($this->app->make(PermissionSynchronizer::class), $this->app->make(UserProvisioningService::class));
        $this->assertSame(['+22370000002'], $fresh->ensure());
        $this->assertSame(2, User::count());
    }

    public function test_a_soft_deleted_superadmin_is_not_recreated(): void
    {
        $this->configure();
        $this->bootstrapper()->ensure();
        $second = User::where('phone', '+22370000002')->firstOrFail();
        $second->delete(); // le premier reste actif : la suppression est permise

        $fresh = new SuperadminBootstrapper($this->app->make(PermissionSynchronizer::class), $this->app->make(UserProvisioningService::class));
        $this->assertSame([], $fresh->ensure());
        $this->assertSame(1, User::count());
    }

    public function test_it_does_nothing_without_configuration_or_when_disabled(): void
    {
        config(['superadmins.password' => null, 'superadmins.accounts' => [['name' => null, 'phone' => null], ['name' => null, 'phone' => null]]]);
        $this->assertSame([], $this->bootstrapper()->ensure());

        $this->configure();
        config(['superadmins.auto_create' => false]);
        $this->assertSame([], $this->app->make(SuperadminBootstrapper::class)->ensure());

        $this->configure();
        config(['superadmins.password' => '']);
        $this->assertSame([], $this->app->make(SuperadminBootstrapper::class)->ensure());

        $this->assertSame(0, User::count());
    }

    public function test_an_invalid_configuration_never_crashes_and_creates_nothing_invalid(): void
    {
        Log::spy();
        $this->configure('local', '123'); // trop court : refusé par la règle D14

        $this->assertSame([], $this->bootstrapper()->ensure());
        $this->assertSame(0, User::count());
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_a_weak_default_password_outside_development_is_reported_in_the_logs(): void
    {
        Log::spy();
        $this->configure('production', 'password123');

        $this->bootstrapper()->ensure();

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'faible'))->once();
    }

    public function test_starting_the_development_server_creates_them(): void
    {
        $this->configure();

        event(new CommandStarting('serve', new ArrayInput([]), new NullOutput));

        $this->assertSame(2, User::count());
    }

    public function test_other_commands_starting_do_not_create_anything(): void
    {
        $this->configure();

        event(new CommandStarting('route:list', new ArrayInput([]), new NullOutput));

        $this->assertSame(0, User::count());
    }

    public function test_a_successful_migration_creates_them_but_a_failed_one_does_not(): void
    {
        $this->configure();

        event(new CommandFinished('migrate', new ArrayInput([]), new NullOutput, 1));
        $this->assertSame(0, User::count());

        event(new CommandFinished('migrate', new ArrayInput([]), new NullOutput, 0));
        $this->assertSame(2, User::count());
    }

    public function test_the_first_visit_of_the_login_page_creates_them_then_they_can_log_in(): void
    {
        $this->configure('local');
        $this->assertSame(0, User::count());

        $this->get('/login')->assertOk();
        $this->assertSame(2, User::count());

        $this->post('/login', ['_token' => csrf_token(), 'phone' => '70 00 00 01', 'password' => 'Fixture-Test-1'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_when_hosted_the_first_login_forces_the_password_change(): void
    {
        $this->configure('production');

        $this->get('/login')->assertOk();
        $this->post('/login', ['_token' => csrf_token(), 'phone' => '+223 70 00 00 02', 'password' => 'Fixture-Test-1'])->assertRedirect('/');

        $this->get('/')->assertRedirect('/password/change');
        $this->put('/password/change', ['_token' => csrf_token(), 'current_password' => 'Fixture-Test-1', 'password' => 'Mot-De-Passe-Fort-9', 'password_confirmation' => 'Mot-De-Passe-Fort-9'])->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    public function test_the_database_seeder_creates_roles_permissions_and_the_configured_superadmins(): void
    {
        $this->configure();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Role::count());
        $this->assertSame(2, User::count());
        $this->assertTrue(ActivityLog::where('action', 'users.role_assigned')->exists());
    }

    public function test_versioned_files_contain_no_credential(): void
    {
        foreach (['config/superadmins.php', 'database/seeders/SuperadminSeeder.php', '.env.example', 'phpunit.xml'] as $file) {
            $this->assertStringNotContainsString('admin123', file_get_contents(base_path($file)), "$file ne doit contenir aucun mot de passe.");
        }

        // Aucun numéro de téléphone en clair, nulle part où le code est versionné (la liste noire de mots de passe faibles n'est pas un identifiant).
        foreach (['config/superadmins.php', 'app/Services/Users/SuperadminBootstrapper.php', 'database/seeders/SuperadminSeeder.php', '.env.example', 'phpunit.xml'] as $file) {
            $this->assertDoesNotMatchRegularExpression('/\+223\d{8}/', file_get_contents(base_path($file)), "$file ne doit contenir aucun numéro.");
        }
    }
}
