<?php

namespace Tests\Feature\Web;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Dashboard\MockDashboardData;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

class DashboardAndModulesTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_the_superadmin_supervision_shows_the_single_superadmin_alert(): void
    {
        $this->actingAs($this->superadmin())->get('/')
            ->assertOk()
            ->assertSee('Supervision générale')
            ->assertSee('Un seul superadmin actif');
    }

    public function test_no_alert_when_two_superadmins_are_active(): void
    {
        $this->superadmin();
        $second = $this->superadmin();

        $this->actingAs($second)->get('/')->assertOk()->assertDontSee('Un seul superadmin actif');
    }

    public function test_no_fictitious_data_remains(): void
    {
        $this->assertFalse(class_exists(MockDashboardData::class));
        $this->assertNull(config('dashboard.mock'));
        $this->actingAs($this->superadmin())->get('/')->assertOk()->assertDontSee('Données fictives');
    }

    public function test_the_sidebar_lists_the_planned_evolutions_by_role(): void
    {
        // Les six modules de la V1 sont livrés : la liste « à venir » ne contient plus que les évolutions du cahier §27.
        $this->assertSame([], array_intersect(array_keys(config('planned_modules')), ['articles', 'fournisseurs', 'entrees', 'sorties', 'stock', 'rapports']));
        $expected = collect(config('planned_modules'))->pluck('label');

        foreach ([$this->superadmin(), User::factory()->administrateur()->create()] as $user) {
            $page = $this->actingAs($user)->get('/')->assertOk()->assertSee('Modules à venir');
            foreach ($expected as $label) {
                $page->assertSee($label);
            }
        }

        // Le magasinier ne voit que les évolutions qui le concernent.
        $this->actingAs(User::factory()->magasinier()->create())->get('/')->assertSee('Inventaires physiques')->assertSee('Demandes de matériel')->assertDontSee('Patrimoine');
    }

    public function test_a_planned_module_page_is_a_sheet_with_open_points_and_no_data(): void
    {
        $this->actingAs($this->superadmin())->get('/modules/inventaires')
            ->assertOk()
            ->assertSee('À venir')
            ->assertSee('Fréquence et périmètre des inventaires')
            ->assertSee('config/modules/inventaires.php');
    }

    public function test_an_unknown_planned_module_is_a_404(): void
    {
        $this->get('/modules/inventaires')->assertRedirect('/login'); // invité, avant toute authentification
        $this->actingAs(User::factory()->magasinier()->create())->get('/modules/n-existe-pas')->assertNotFound();
    }

    public function test_a_module_leaves_the_planned_list_when_its_manifest_exists(): void
    {
        $superadmin = $this->superadmin();
        config(['modules.qr-code' => ['label' => 'QR codes', 'permissions' => ['view' => 'Voir'], 'roles' => []]]);

        $this->actingAs($superadmin)->get('/modules/qr-code')->assertNotFound();
        $this->actingAs($superadmin)->get('/modules/inventaires')->assertOk();
    }

    /** Le HTML de la sidebar seule (le corps de la page peut contenir les mêmes mots). */
    private function sidebar(string $html): string
    {
        preg_match('#<div class="sidebar-wrapper".*?</ul>\s*</div>#s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_the_settings_entry_replaces_roles_and_sits_below_every_other_menu(): void
    {
        $sidebar = $this->sidebar($this->actingAs($this->superadmin())->get('/')->getContent());

        $this->assertStringContainsString('Paramètres', $sidebar);
        $this->assertStringNotContainsString('>Rôles<', $sidebar, 'L\'entrée « Rôles » est devenue « Paramètres ».');

        // Sous « Tableau de bord », sous tous les modules à venir, en dernier.
        $lastPlanned = collect(config('planned_modules'))->pluck('label')->map(fn ($label) => strrpos($sidebar, $label))->max();
        $this->assertGreaterThan($lastPlanned, strrpos($sidebar, 'Paramètres'));
        $this->assertGreaterThan(strpos($sidebar, 'Modules à venir'), strpos($sidebar, 'Paramètres'));
        $this->assertNotSame('', $sidebar);
    }

    public function test_permissions_and_assignment_are_not_in_the_sidebar_but_in_the_settings_menu(): void
    {
        $superadmin = $this->superadmin();

        $sidebar = $this->sidebar($this->actingAs($superadmin)->get('/')->getContent());
        $this->assertStringNotContainsString('Attribution', $sidebar);
        $this->assertStringNotContainsString('bx-shield-alt-2', $sidebar);
        $this->assertStringNotContainsString('bx-user-check', $sidebar);
        // Audit et Système non plus : ils s'ouvrent depuis Paramètres.
        $this->assertStringNotContainsString('bx-list-check', $sidebar);
        $this->assertStringNotContainsString('bx-server', $sidebar);

        // Ils restent accessibles depuis la zone Paramètres.
        $page = $this->get('/roles')->assertOk()->assertSee('PARAMÈTRES');
        $page->assertSee('Permissions')->assertSee('Attribution des permissions')->assertSee('Rôles')
            ->assertSee(route('audit.index'))->assertSee(route('system.index'));
    }

    public function test_audit_and_system_are_reached_from_the_settings_menu_and_show_it(): void
    {
        $superadmin = $this->superadmin();

        foreach (['/audit' => 'audit.index', '/system' => 'system.index'] as $uri => $route) {
            $this->actingAs($superadmin)->get($uri)->assertOk()
                ->assertSee('PARAMÈTRES')
                ->assertSee('list-group-item list-group-item-action active', false)
                ->assertSee(route($route));
        }
    }

    public function test_settings_stays_highlighted_on_every_settings_page(): void
    {
        $superadmin = $this->superadmin();

        foreach (['/roles', '/permissions', '/user-permissions', '/audit', '/system'] as $uri) {
            $sidebar = $this->sidebar($this->actingAs($superadmin)->get($uri)->getContent());
            $this->assertMatchesRegularExpression('#mm-active[^>]*>\s*<div class="parent-icon"><i class=\'bx bx-cog\'#', $sidebar, "Paramètres doit être actif sur $uri");
        }
    }

    public function test_the_settings_entry_is_hidden_from_roles_without_access(): void
    {
        // L'administrateur voit Paramètres (Utilisateurs) ; le magasinier n'a par défaut aucun de ces écrans.
        $sidebar = $this->sidebar($this->actingAs(User::factory()->magasinier()->create())->get('/')->getContent());

        $this->assertStringNotContainsString('Paramètres', $sidebar);
    }

    public function test_the_settings_entry_target_is_not_hardcoded_to_roles(): void
    {
        // ModuleRegistry::firstAccessibleSettingsItem() : fail-closed sans aucun accès, et le premier écran
        // réellement accessible sinon — jamais figé sur /roles (réservée au superadmin).
        $registry = app(ModuleRegistry::class);

        $this->assertNull($registry->firstAccessibleSettingsItem(User::factory()->magasinier()->create()));

        $item = $registry->firstAccessibleSettingsItem($this->superadmin());
        $this->assertNotNull($item);
        $this->assertContains($item['route'], collect($registry->settingsItems())->pluck('route')->all());
    }

    public function test_the_profile_shows_the_role(): void
    {
        $magasinier = User::factory()->magasinier()->create(['name' => 'Magasinier Test']);

        $this->actingAs($magasinier)->get('/profile')->assertOk()->assertSee('Magasinier Test')->assertSee('Comptable matière');
    }

    public function test_pages_never_reference_the_internet(): void
    {
        $login = $this->get('/login')->getContent();
        $pages = $login.$this->actingAs($this->superadmin())->get('/')->getContent().$this->get('/profile')->getContent().$this->get('/roles')->getContent().$this->get('/permissions')->getContent();

        preg_match_all('#(?:src|href|action)=["\'](https?://[^"\']+)#', $pages, $matches);
        // asset() et route() produisent des URL absolues sur le serveur lui-même : seules les URL vers un AUTRE hôte sont interdites.
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $external = array_values(array_unique(array_filter($matches[1], fn ($url) => parse_url($url, PHP_URL_HOST) !== $appHost)));
        $this->assertSame([], $external, 'Ressource externe dans une page (D15).');
        foreach (['fonts.googleapis', 'unpkg.com', 'jsdelivr', 'cdnjs', 'code.jquery.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $pages);
        }
    }

    public function test_the_audit_table_of_the_supervision_lists_real_entries(): void
    {
        $superadmin = $this->superadmin(['name' => 'Moustapha Test']);
        $this->actingAs($superadmin);
        User::factory()->administrateur()->create(['name' => 'Cible Test']);

        $this->get('/')->assertOk()->assertSee('DERNIÈRES ACTIONS')->assertSee('Cible Test');
        $this->assertGreaterThan(0, ActivityLog::count());
    }
}
