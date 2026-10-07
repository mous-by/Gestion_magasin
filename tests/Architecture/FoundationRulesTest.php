<?php

namespace Tests\Architecture;

use App\Models\ActivityLog;
use App\Models\Concerns\LogsActivity;
use App\Models\DocumentSequence;
use App\Models\MouvementStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Garde-fous du socle (CLAUDE.md §4) : ils échouent si une règle est enfreinte.
 */
class FoundationRulesTest extends TestCase
{
    public function test_every_business_model_uses_logs_activity(): void
    {
        // Exemptés : les journaux eux-mêmes (audit, mouvements de stock, non modifiables) et le compteur technique de
        // numérotation (le numéro attribué est audité avec son document).
        $exempt = [ActivityLog::class, MouvementStock::class, DocumentSequence::class];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! is_subclass_of($class, Model::class) || in_array($class, $exempt, true)) {
                continue;
            }

            $this->assertContains(LogsActivity::class, class_uses_recursive($class), "$class doit utiliser LogsActivity (audit).");
        }
    }

    public function test_no_bulk_update_or_delete_on_the_audit_log(): void
    {
        foreach ($this->phpFiles(app_path()) as $file) {
            $code = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/ActivityLog::(query\(\)->)?(where|whereIn|whereKey)[^;]*->(update|delete|forceDelete|truncate)\(/s', $code, "$file : mise à jour ou suppression de masse de l'audit interdite.");
            $this->assertDoesNotMatchRegularExpression('/ActivityLog::(truncate|destroy)\(/', $code, "$file : suppression de l'audit interdite.");
            // Même règle pour le journal des mouvements de stock (RG05).
            $this->assertDoesNotMatchRegularExpression('/MouvementStock::(query\(\)->)?(where|whereIn|whereKey)[^;]*->(update|delete|forceDelete|truncate)\(/s', $code, "$file : mise à jour ou suppression de masse des mouvements interdite.");
            $this->assertDoesNotMatchRegularExpression('/MouvementStock::(truncate|destroy)\(/', $code, "$file : suppression des mouvements interdite.");
        }
    }

    public function test_no_weak_password_rule_anywhere(): void
    {
        foreach ($this->phpFiles(app_path()) as $file) {
            if (preg_match_all('/Password::min\((\d+)\)/', file_get_contents($file), $m)) {
                foreach ($m[1] as $length) {
                    $this->assertGreaterThanOrEqual(8, (int) $length, "$file : Password::min($length) < 8 (D14).");
                }
            }
        }
    }

    public function test_views_and_local_assets_load_nothing_from_the_internet(): void
    {
        $offenders = [];

        foreach ($this->filesIn(resource_path('views'), ['php']) as $file) {
            // xmlns SVG mis à part, aucune URL absolue dans les vues.
            $code = preg_replace('#https?://www\.w3\.org/[^\s"\']*#', '', file_get_contents($file));
            if (preg_match('#https?://#', $code)) {
                $offenders[] = $file;
            }
        }

        foreach ([public_path('assets/css'), public_path('assets/plugins')] as $dir) {
            foreach ($this->filesIn($dir, ['css']) as $file) {
                if (preg_match('#(url\(\s*[\'"]?https?:|@import[^;]*https?:)#i', file_get_contents($file))) {
                    $offenders[] = $file;
                }
            }
        }

        $this->assertSame([], $offenders, 'Ressources externes interdites (D15) : '.implode(', ', $offenders));
    }

    public function test_every_route_is_authenticated_and_permission_protected_or_explicitly_allowed(): void
    {
        // Routes accessibles à tout utilisateur connecté, sans permission métier (CLAUDE.md §4).
        $authenticatedOnly = ['home', 'logout', 'password.change', 'password.change.update', 'profile.show', 'profile.update', 'profile.password', 'modules.show'];
        // Réservées aux invités (un compte déjà connecté en est redirigé) : seulement la connexion.
        $guestOnly = ['login', 'login.store'];
        // Publiques sans restriction sur l'état de connexion : aucune en V1.
        $publicWeb = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($route->uri() === 'up' || str_starts_with((string) $name, 'storage.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (in_array($name, $publicWeb, true)) {
                continue;
            }

            if (in_array($name, $guestOnly, true)) {
                $this->assertContains('guest', $middleware, "{$route->uri()} doit être réservée aux invités.");

                continue;
            }

            $this->assertContains('auth', $middleware, "{$route->uri()} doit exiger l'authentification.");
            $this->assertContains('active', $middleware, "{$route->uri()} doit vérifier que le compte est actif.");

            $hasPermission = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
            $this->assertTrue($hasPermission || in_array($name, $authenticatedOnly, true), "{$route->uri()} ({$name}) doit porter permission:module.action ou figurer dans la liste blanche.");
        }
    }

    /**
     * Un <div> jamais refermé (trouvé sur l'écran Utilisateurs de VigiMoto : les modales et le pied de page se
     * retrouvaient emboîtés dans une colonne de la grille, page cassée après un clic) casse la mise en page
     * sans provoquer d'erreur PHP — Blade ne vérifie pas le HTML généré. Compte grossier (une balise par ligne
     * de code, pas dans le HTML rendu) : assez pour repérer un oubli, pas pour valider une structure fine.
     */
    public function test_every_view_has_balanced_div_tags(): void
    {
        $offenders = [];

        foreach ($this->filesIn(resource_path('views'), ['php']) as $file) {
            $code = file_get_contents($file);
            $depth = preg_match_all('/<div\b/', $code) - preg_match_all('#</div>#', $code);
            if ($depth !== 0) {
                $offenders[] = "$file ($depth)";
            }
        }

        $this->assertSame([], $offenders, 'Balises <div> déséquilibrées : '.implode(', ', $offenders));
    }

    /**
     * Piège de Blade : une directive `@php(...)` en ligne suivie, dans la même vue, d'un bloc `@php … @endphp` est mal
     * compilée (variables « indéfinies », erreur de syntaxe). Trouvé sur les écrans des référentiels (phase 3).
     */
    public function test_no_view_mixes_inline_php_before_a_php_block(): void
    {
        $offenders = [];

        foreach ($this->filesIn(resource_path('views'), ['php']) as $file) {
            $code = file_get_contents($file);
            $inline = preg_match('/@php\(/', $code, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : null;
            $block = preg_match('/@php\s*\n/', $code, $match, PREG_OFFSET_CAPTURE, (int) $inline) ? $match[0][1] : null;
            if ($inline !== null && $block !== null) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders, 'Remplacer @php(...) par un bloc @php … @endphp : '.implode(', ', $offenders));
    }

    public function test_no_role_middleware_is_used_in_routes(): void
    {
        foreach (glob(base_path('routes/web/*.php')) as $file) {
            $this->assertStringNotContainsString("'role:", file_get_contents($file), "$file : utiliser permission:module.action, pas role: (CLAUDE.md §4).");
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        return $this->filesIn($dir, ['php']);
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function filesIn(string $dir, array $extensions): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
