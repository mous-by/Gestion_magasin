<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private const PASSWORD = 'Secret-Pass-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    private function login(string $phone, string $password = self::PASSWORD)
    {
        return $this->from('/login')->post('/login', ['phone' => $phone, 'password' => $password]);
    }

    public function test_the_login_page_renders_for_guests_and_the_session_lasts_120_minutes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Connexion')->assertDontSee('Se souvenir de moi');
        $this->assertSame(120, (int) config('session.lifetime'));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_a_staff_member_logs_in_with_their_phone_number_in_any_common_format(): void
    {
        $user = User::factory()->magasinier()->create(['phone' => '+22370000011', 'password' => self::PASSWORD]);

        foreach (['70 00 00 11', '+223 70 00 00 11', '0022370000011', '223-70-00-00-11', '+22370000011'] as $format) {
            $this->login($format)->assertRedirect('/');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
            $this->assertGuest();
        }

        $this->assertNotNull($user->fresh()->last_login_at);
        $log = ActivityLog::where('action', 'auth.login')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('magasinier', $log->user_role);
    }

    public function test_the_login_page_asks_for_a_phone_number_not_a_username(): void
    {
        $this->get('/login')->assertOk()->assertSee('Numéro de téléphone')->assertSee('name="phone"', false)->assertDontSee('Identifiant');
    }

    public function test_an_unusable_number_is_refused_like_a_wrong_password(): void
    {
        User::factory()->administrateur()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        foreach (['abcdef', '123', '70 00'] as $bad) {
            $this->login($bad)->assertSessionHasErrors(['phone' => trans('auth.failed')]);
        }
        $this->assertGuest();
    }

    public function test_the_lockout_counts_every_format_of_the_same_number_together(): void
    {
        User::factory()->administrateur()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        foreach (['70 00 00 01', '+22370000001', '0022370000001', '70000001', '+223 70 00 00 01'] as $format) {
            $this->login($format, 'faux-mot-de-passe-1');
        }

        $this->login('70000001')->assertSessionHasErrors('phone');
        $this->assertGuest();
        $this->assertSame(1, ActivityLog::where('action', 'auth.lockout')->count());
    }

    public function test_a_wrong_password_is_refused_and_audited_without_the_password(): void
    {
        User::factory()->superadmin()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        $this->login('+22370000001', 'mauvais-mot-de-passe-1')->assertRedirect('/login')->assertSessionHasErrors('phone');

        $this->assertGuest();
        $log = ActivityLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame('+22370000001', $log->new_values['phone']);
        $this->assertStringNotContainsString('mauvais-mot-de-passe-1', json_encode(ActivityLog::all()->toArray()));
    }

    public function test_an_unknown_user_gets_the_same_message_as_a_wrong_password(): void
    {
        User::factory()->superadmin()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        $this->login('+22370000001', 'faux-mot-de-passe-1')->assertSessionHasErrors(['phone' => trans('auth.failed')]);
        $this->login('+22370000099', 'faux-mot-de-passe-1')->assertSessionHasErrors(['phone' => trans('auth.failed')]);
    }

    public function test_five_failures_lock_the_login_even_with_the_right_password(): void
    {
        User::factory()->superadmin()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        for ($i = 0; $i < 5; $i++) {
            $this->login('+22370000001', 'faux-mot-de-passe-1')->assertSessionHasErrors('phone');
        }

        $response = $this->login('+22370000001')->assertSessionHasErrors('phone');
        $this->assertStringContainsString('trop nombreuses', session('errors')->first('phone'));
        $this->assertGuest();
        $this->assertSame(1, ActivityLog::where('action', 'auth.lockout')->count());
    }

    public function test_a_successful_login_resets_the_failure_counter(): void
    {
        User::factory()->superadmin()->create(['phone' => '+22370000001', 'password' => self::PASSWORD]);

        for ($i = 0; $i < 4; $i++) {
            $this->login('+22370000001', 'faux-mot-de-passe-1');
        }
        $this->login('+22370000001')->assertRedirect('/');
        $this->post('/logout');

        for ($i = 0; $i < 4; $i++) {
            $this->login('+22370000001', 'faux-mot-de-passe-1')->assertSessionHasErrors('phone');
        }
        $this->assertSame(0, ActivityLog::where('action', 'auth.lockout')->count());
    }

    public function test_a_deactivated_account_cannot_log_in_and_the_block_is_audited(): void
    {
        User::factory()->administrateur()->inactive()->create(['phone' => '+22370000021', 'password' => self::PASSWORD]);

        $this->login('+22370000021')->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertStringContainsString('désactivé', session('errors')->first('phone'));
        $this->assertSame('disabled', ActivityLog::where('action', 'auth.login_blocked')->firstOrFail()->new_values['reason']);
    }

    public function test_a_soft_deleted_account_cannot_log_in(): void
    {
        $user = User::factory()->administrateur()->create(['phone' => '+22370000031', 'password' => self::PASSWORD]);
        $user->delete();

        $this->login('+22370000031')->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_an_account_without_role_is_refused_fail_closed(): void
    {
        User::factory()->create(['phone' => '+22370000051', 'password' => self::PASSWORD]);

        $this->login('+22370000051')->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_a_deactivated_user_is_kicked_out_of_a_live_session(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $user = User::factory()->administrateur()->create();

        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['is_active' => false]);
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_is_audited(): void
    {
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, ActivityLog::where('action', 'auth.logout')->where('user_id', $user->id)->count());
    }

    public function test_a_temporary_password_forces_the_change_before_anything_else(): void
    {
        $user = User::factory()->administrateur()->mustChangePassword()->create(['phone' => '+22370000061', 'password' => self::PASSWORD]);

        $this->login('+22370000061')->assertRedirect('/');
        $this->get('/')->assertRedirect('/password/change');
        $this->get('/profile')->assertRedirect('/password/change');
        $this->get('/password/change')->assertOk()->assertSee('temporaire');

        $this->put('/password/change', ['current_password' => self::PASSWORD, 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'Nouveau-Secret-9'])
            ->assertRedirect('/');

        $this->get('/')->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Nouveau-Secret-9', $user->fresh()->password));
        $this->assertSame(1, ActivityLog::where('action', 'auth.password_changed')->count());
    }

    public function test_the_new_password_must_be_compliant_and_different(): void
    {
        $user = User::factory()->administrateur()->create(['password' => self::PASSWORD]);
        $this->actingAs($user);

        foreach (['court1', 'seulement-des-lettres', '1234567890', self::PASSWORD] as $bad) {
            $this->put('/profile/password', ['current_password' => self::PASSWORD, 'password' => $bad, 'password_confirmation' => $bad])
                ->assertSessionHasErrors('password');
        }
        $this->put('/profile/password', ['current_password' => 'incorrect-1', 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'Nouveau-Secret-9'])
            ->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => self::PASSWORD, 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'autre-Secret-9'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_changing_the_password_closes_the_other_sessions(): void
    {
        $user = User::factory()->administrateur()->create(['password' => self::PASSWORD]);
        DB::table('sessions')->insert(['id' => 'ailleurs', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->put('/profile/password', ['current_password' => self::PASSWORD, 'password' => 'Nouveau-Secret-9', 'password_confirmation' => 'Nouveau-Secret-9'])
            ->assertRedirect('/profile');

        $this->assertSame(0, DB::table('sessions')->where('id', 'ailleurs')->count());
    }
}
