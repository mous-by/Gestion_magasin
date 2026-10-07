<?php

namespace Tests\Feature\Audit;

use App\Enums\ActivityChannel;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * journal d'audit consultable par le superadmin seulement, filtrable, détail en modale.
 */
class AuditScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->superadmin = $this->superadmin(['name' => 'Chef Système']);
    }

    /**
     * Écrit une ligne d'audit à une date donnée (created_at n'est pas modifiable après coup).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes = [], ?string $at = null): ActivityLog
    {
        $log = new ActivityLog($attributes + [
            'user_name' => 'Agent Test',
            'user_role' => 'administrateur',
            'action' => 'users.updated',
            'module' => 'users',
            'description' => 'Modification — Utilisateur « Test »',
            'channel' => ActivityChannel::Web,
        ]);
        $log->forceFill(['created_at' => $at ? Carbon::parse($at) : now()])->save();

        return $log;
    }

    public function test_the_screen_is_reserved_to_the_superadmin(): void
    {
        $this->get('/audit')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrateur()->create())->get('/audit')->assertForbidden();
        $this->actingAs(User::factory()->magasinier()->create())->get('/audit')->assertForbidden();
        $this->actingAs($this->superadmin)->get('/audit')->assertOk()->assertSee('JOURNAL D\'AUDIT', false);
    }

    public function test_entries_are_listed_newest_first_and_superadmin_actions_are_flagged(): void
    {
        $this->entry(['description' => 'Ancienne action'], '2026-01-01 10:00:00');
        $this->entry(['description' => 'Action du chef', 'user_name' => 'Chef Système', 'user_role' => 'superadmin'], '2026-02-01 10:00:00');

        $response = $this->actingAs($this->superadmin)->get('/audit');

        $response->assertOk()->assertSeeInOrder(['Action du chef', 'Ancienne action'])->assertSee('Superadmin');
    }

    public function test_it_follows_the_theme_template(): void
    {
        $this->entry();

        $this->actingAs($this->superadmin)->get('/audit')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('id="auditDetailModal"', false)
            ->assertSee('class="modal-header"', false);
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $this->entry(['description' => 'Connexion réussie', 'action' => 'auth.login', 'module' => 'auth', 'user_name' => 'Awa Diallo'], '2026-03-10 09:00:00');
        $this->entry(['description' => 'Rôle attribué', 'action' => 'users.role_assigned', 'module' => 'users', 'channel' => ActivityChannel::Console], '2026-04-10 09:00:00');
        $this->entry(['description' => 'Action superadmin', 'user_role' => 'superadmin', 'user_name' => 'Chef Système', 'action' => 'permissions.created', 'module' => 'permissions'], '2026-05-10 09:00:00');

        $get = fn (array $query) => $this->actingAs($this->superadmin)->get('/audit?'.http_build_query($query));

        $get(['module' => 'auth'])->assertSee('Connexion réussie')->assertDontSee('Rôle attribué');
        $get(['action' => 'role_assigned'])->assertSee('Rôle attribué')->assertDontSee('Connexion réussie');
        $get(['author' => 'awa'])->assertSee('Connexion réussie')->assertDontSee('Rôle attribué');
        $get(['channel' => 'console'])->assertSee('Rôle attribué')->assertDontSee('Connexion réussie');
        $get(['from' => '2026-04-01', 'to' => '2026-04-30'])->assertSee('Rôle attribué')->assertDontSee('Connexion réussie')->assertDontSee('Action superadmin');
        $get(['superadmin' => '1'])->assertSee('Action superadmin')->assertDontSee('Rôle attribué');
    }

    public function test_like_wildcards_typed_in_a_filter_are_taken_literally(): void
    {
        $this->entry(['description' => 'Première', 'action' => 'users.updated']);

        $this->actingAs($this->superadmin)->get('/audit?'.http_build_query(['action' => '%']))->assertDontSee('Première');
    }

    public function test_an_inverted_date_range_is_refused(): void
    {
        $this->actingAs($this->superadmin)->from('/audit')->get('/audit?from=2026-05-10&to=2026-05-01')->assertSessionHasErrors('to');
        $this->actingAs($this->superadmin)->from('/audit')->get('/audit?channel=telegram')->assertSessionHasErrors('channel');
    }

    public function test_the_list_is_paginated_and_keeps_the_filters(): void
    {
        foreach (range(1, 30) as $i) {
            $this->entry(['description' => "Ligne {$i}", 'module' => 'users'], now()->subMinutes(30 - $i)->toDateTimeString());
        }

        $first = $this->actingAs($this->superadmin)->get('/audit?module=users');
        $first->assertSee('Ligne 30')->assertDontSee('Ligne 1<', false)->assertSee('module=users&amp;page=2', false);

        $this->actingAs($this->superadmin)->get('/audit?module=users&page=2')->assertSee('Ligne 1<', false)->assertDontSee('Ligne 30');
    }

    public function test_the_detail_carries_the_old_and_new_values_for_the_modal(): void
    {
        $this->entry(['description' => 'Modification', 'old_values' => ['name' => 'Ancien nom'], 'new_values' => ['name' => 'Nouveau nom'], 'ip_address' => '10.0.0.7']);

        $this->actingAs($this->superadmin)->get('/audit')
            ->assertSee('Ancien nom')
            ->assertSee('Nouveau nom')
            ->assertSee('10.0.0.7');
    }

    public function test_text_typed_by_a_third_party_is_escaped(): void
    {
        $this->entry(['description' => '<script>alert(1)</script>', 'new_values' => ['phone' => '"><img src=x onerror=alert(2)>']]);

        $this->actingAs($this->superadmin)->get('/audit')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_consulting_the_journal_writes_nothing_to_it(): void
    {
        $this->entry();
        $before = ActivityLog::count();

        $this->actingAs($this->superadmin)->get('/audit')->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }
}
