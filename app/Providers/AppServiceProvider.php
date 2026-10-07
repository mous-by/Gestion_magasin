<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Entree;
use App\Models\EntreeDetail;
use App\Models\Fournisseur;
use App\Models\Permission;
use App\Models\Regularisation;
use App\Models\Service;
use App\Models\Sortie;
use App\Models\SortieDetail;
use App\Models\Unite;
use App\Models\User;
use App\Services\Users\SuperadminBootstrapper;
use App\Support\DocumentSettings;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SuperadminBootstrapper::class);
        $this->app->singleton(DocumentSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le superadmin contourne l'autorisation (Gate::before), pas la logique métier ni l'audit (CLAUDE.md §4, D16).
        Gate::before(fn ($user, string $ability) => $user instanceof User && $user->isSuperadmin() ? true : null);

        // D14 : 8 caractères au moins, lettres et chiffres.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Superadmins créés automatiquement s'ils n'existent pas (D27) : au lancement du serveur de développement et après
        // chaque migration (déploiement). La première visite de la page de connexion est le filet pour l'hébergement Web.
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->command === 'serve') {
                app(SuperadminBootstrapper::class)->ensure();
            }
        });
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            if ($event->exitCode === 0 && in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh', 'permissions:sync'], true)) {
                app(SuperadminBootstrapper::class)->ensure();
            }
        });

        // Alias morph stables : ils s'écrivent dans audit_logs et model_has_roles. Chaque module y ajoute ses modèles
        // depuis son propre fournisseur ou ici (CLAUDE.md §6).
        Relation::morphMap([
            'user' => User::class,
            'article' => Article::class,
            'category' => Category::class,
            'unite' => Unite::class,
            'fournisseur' => Fournisseur::class,
            'service' => Service::class,
            'entree' => Entree::class,
            'entree_detail' => EntreeDetail::class,
            'sortie' => Sortie::class,
            'sortie_detail' => SortieDetail::class,
            'regularisation' => Regularisation::class,
            'permission' => Permission::class,
            'role' => Role::class,
        ]);
    }
}
