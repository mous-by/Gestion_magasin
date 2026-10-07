<?php

namespace Tests\Feature\Console;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperadminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_superadmin_interactively_with_a_hashed_password_and_an_audit_trail(): void
    {
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nom complet', 'Moustapha BARRY')
            ->expectsQuestion('Numéro de téléphone (identifiant de connexion, ex. 70 00 00 01)', '70 00 00 01')
            ->expectsQuestion('Mot de passe', 'Secret-Pass-1')
            ->expectsQuestion('Confirmer le mot de passe', 'Secret-Pass-1')
            ->assertExitCode(0);

        $user = User::where('phone', '+22370000001')->firstOrFail();
        $this->assertTrue($user->isSuperadmin());
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertNotSame('Secret-Pass-1', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Secret-Pass-1', $user->password));
        $this->assertTrue(ActivityLog::where('action', 'users.created')->where('subject_id', $user->id)->exists());
        $this->assertTrue(ActivityLog::where('action', 'users.role_assigned')->where('subject_id', $user->id)->exists());
        $this->assertStringNotContainsString('Secret-Pass-1', json_encode(ActivityLog::all()->toArray()), 'Le mot de passe n\'apparaît jamais dans l\'audit.');
    }

    public function test_a_weak_password_is_refused(): void
    {
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nom complet', 'Test')
            ->expectsQuestion('Numéro de téléphone (identifiant de connexion, ex. 70 00 00 01)', '70 00 00 05')
            ->expectsQuestion('Mot de passe', 'abc')
            ->expectsQuestion('Confirmer le mot de passe', 'abc')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nom complet', 'Test')
            ->expectsQuestion('Numéro de téléphone (identifiant de connexion, ex. 70 00 00 01)', '70 00 00 05')
            ->expectsQuestion('Mot de passe', 'Secret-Pass-1')
            ->expectsQuestion('Confirmer le mot de passe', 'Secret-Pass-2')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_the_username_must_be_unique(): void
    {
        User::factory()->superadmin()->create(['phone' => '+22370000001']);

        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nom complet', 'Test')
            ->expectsQuestion('Numéro de téléphone (identifiant de connexion, ex. 70 00 00 01)', '+223 70 00 00 01')
            ->expectsQuestion('Mot de passe', 'Secret-Pass-1')
            ->expectsQuestion('Confirmer le mot de passe', 'Secret-Pass-1')
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
    }

    public function test_it_refuses_to_run_without_interaction_so_no_password_goes_through_arguments(): void
    {
        $this->artisan('app:create-superadmin --no-interaction --name=X --phone=70000007')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
