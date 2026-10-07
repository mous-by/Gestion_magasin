<?php

namespace Tests\Feature\Web;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Profil : deux onglets, « Mes informations » (nom, numéro) et « Mot de passe ». Le numéro est l'identifiant de
 * connexion (D26) : le changer exige le mot de passe actuel.
 */
class ProfileTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private const PASSWORD = 'Secret-Pass-1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->user = User::factory()->magasinier()
            ->create(['name' => 'Chef Test', 'phone' => '+22370000001', 'password' => self::PASSWORD]);
    }

    private function update(array $data)
    {
        return $this->actingAs($this->user)->from('/profile')->put('/profile', $data);
    }

    public function test_the_profile_has_an_information_tab_and_a_password_tab(): void
    {
        $this->actingAs($this->user)->get('/profile')
            ->assertOk()
            ->assertSee('Mes informations')
            ->assertSee('Mot de passe')
            ->assertSee('id="tab-info"', false)
            ->assertSee('id="tab-password"', false)
            ->assertSee('data-active="info"', false)
            ->assertSee('Chef Test')
            ->assertSee('Magasinier');
    }

    public function test_a_user_updates_their_name_without_needing_the_password(): void
    {
        $this->update(['name' => '  Nouveau Nom  ', 'phone' => '+22370000001'])->assertRedirect('/profile')->assertSessionHas('tab', 'info');

        $this->assertSame('Nouveau Nom', $this->user->fresh()->name);
        $log = ActivityLog::where('action', 'users.updated')->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'Chef Test'], $log->old_values);
        $this->assertSame(['name' => 'Nouveau Nom'], $log->new_values);
        $this->assertSame($this->user->id, $log->user_id);
    }

    public function test_changing_the_phone_requires_the_current_password(): void
    {
        $this->update(['name' => 'Chef Test', 'phone' => '70 00 00 09'])->assertSessionHasErrors('phone_password');
        $this->assertSame('+22370000001', $this->user->fresh()->phone);

        $this->update(['name' => 'Chef Test', 'phone' => '70 00 00 09', 'phone_password' => 'mauvais-mot-de-passe-1'])->assertSessionHasErrors('phone_password');
        $this->assertSame('+22370000001', $this->user->fresh()->phone);

        $this->update(['name' => 'Chef Test', 'phone' => '70 00 00 09', 'phone_password' => self::PASSWORD])->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertSame('+22370000009', $this->user->fresh()->phone, 'Le numéro est normalisé en E.164.');
    }

    public function test_after_a_phone_change_the_new_number_is_the_login_and_the_old_one_no_longer_works(): void
    {
        $this->update(['name' => 'Chef Test', 'phone' => '+22370000009', 'phone_password' => self::PASSWORD]);
        $this->post('/logout');

        $this->post('/login', ['phone' => '70 00 00 01', 'password' => self::PASSWORD])->assertSessionHasErrors('phone');
        $this->assertGuest();

        $this->post('/login', ['phone' => '70 00 00 09', 'password' => self::PASSWORD])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_the_phone_must_be_valid_and_unique_even_among_deleted_accounts(): void
    {
        User::factory()->create(['phone' => '+22370000002']);
        User::factory()->create(['phone' => '+22370000003'])->delete();

        foreach (['pas-un-numero', '123', '70 00 00 02', '+223 70 00 00 03'] as $bad) {
            $this->update(['name' => 'Chef Test', 'phone' => $bad, 'phone_password' => self::PASSWORD])->assertSessionHasErrors('phone');
        }
        $this->assertSame('+22370000001', $this->user->fresh()->phone);
    }

    public function test_keeping_the_same_number_written_differently_is_not_a_change(): void
    {
        $this->update(['name' => 'Chef Test', 'phone' => '70 00 00 01'])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $this->assertSame(0, ActivityLog::where('action', 'users.updated')->count(), 'Rien n\'a changé : rien n\'est audité.');
    }

    public function test_the_name_is_required_and_bounded(): void
    {
        $this->update(['name' => '', 'phone' => '+22370000001'])->assertSessionHasErrors('name');
        $this->update(['name' => str_repeat('a', 151), 'phone' => '+22370000001'])->assertSessionHasErrors('name');
    }

    public function test_role_and_status_cannot_be_forged_through_the_profile_form(): void
    {
        $this->update([
            'name' => 'Chef Test', 'phone' => '+22370000001',
            'role' => 'superadmin', 'is_active' => 0, 'must_change_password' => 1, 'password' => 'Pirate-Pass-9',
        ])->assertRedirect('/profile');

        $fresh = $this->user->fresh();
        $this->assertTrue($fresh->hasRole('magasinier') && ! $fresh->isSuperadmin());
        $this->assertTrue($fresh->is_active);
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::PASSWORD, $fresh->password));
    }

    public function test_the_password_tab_reopens_on_a_password_error_and_the_information_tab_on_an_information_error(): void
    {
        $this->actingAs($this->user)->from('/profile')->put('/profile/password', ['current_password' => 'incorrect-1', 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'Nouveau-Secret-9']);
        $this->get('/profile')->assertSee('data-active="password"', false);

        $this->update(['name' => '', 'phone' => '+22370000001']);
        $this->get('/profile')->assertSee('data-active="info"', false);
    }

    public function test_after_a_successful_password_change_the_password_tab_stays_open(): void
    {
        $this->actingAs($this->user)->put('/profile/password', ['current_password' => self::PASSWORD, 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'Nouveau-Secret-9'])
            ->assertRedirect('/profile')->assertSessionHas('tab', 'password');
    }

    public function test_the_profile_is_only_for_the_signed_in_user(): void
    {
        $this->put('/profile', ['name' => 'X', 'phone' => '+22370000001'])->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_a_temporary_password_blocks_the_profile_until_it_is_changed(): void
    {
        $temporary = User::factory()->administrateur()->mustChangePassword()->create();

        $this->actingAs($temporary)->get('/profile')->assertRedirect('/password/change');
        $this->put('/profile', ['name' => 'Pirate', 'phone' => $temporary->phone])->assertRedirect('/password/change');
    }
}
