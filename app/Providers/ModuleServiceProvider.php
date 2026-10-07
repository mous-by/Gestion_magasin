<?php

namespace App\Providers;

use App\Services\Dashboard\RealDashboardData;
use App\Support\ModuleRegistry;
use App\Support\PlannedModules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Modules par manifeste (D7) : les manifestes config/modules/*.php sont chargés par Laravel (répertoire de
 * configuration imbriqué) ; ce fournisseur expose le registre et alimente le menu de la sidebar.
 * Un module s'ajoute sans modifier ce fichier.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        // Cloche de notifications : seulement ce que l'utilisateur peut traiter ou consulter (RealDashboardData).
        View::composer('partials.navbar', function ($view) {
            $user = Auth::user();
            $view->with('notifications', $user ? app(RealDashboardData::class)->notifications($user) : []);
        });

        View::composer('partials.sidebar', function ($view) {
            $user = Auth::user();
            $registry = app(ModuleRegistry::class);

            $navigation = $user ? $registry->navigation($user) : [];

            // Paramètres n'est pas une entrée de manifeste comme les autres : elle regroupe des écrans à
            // permissions différentes (Utilisateurs, Rôles, Permissions, Audit, Système),
            // donc elle apparaît si l'utilisateur peut en atteindre au moins un, et pointe vers le premier
            // accessible (jamais toujours Rôles, réservée au superadmin).
            if ($user && ($settings = $registry->firstAccessibleSettingsItem($user))) {
                $navigation[] = [
                    'label' => 'Paramètres',
                    'icon' => 'bx bx-cog',
                    'route' => $settings['route'],
                    // 'users.permissions.*' : route de redirection historique (/users/{user}/permissions),
                    // pas un écran de settingsItems() en soi, mais qui reste sous ce même chapeau.
                    'active' => [...collect($registry->settingsItems())->pluck('active')->all(), 'users.permissions.*'],
                    'position' => 'bottom',
                    'order' => 900,
                ];
            }

            $view->with('navigation', $navigation);
            // Pastilles du menu : brouillons à valider, articles à réapprovisionner (RealDashboardData::sidebarBadges).
            $view->with('sidebarBadges', $user ? app(RealDashboardData::class)->sidebarBadges($user) : []);
            $view->with('plannedModules', $user ? app(PlannedModules::class)->visibleTo($user) : []);
        });
    }
}
