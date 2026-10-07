<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Chaque action reflète la permission du manifeste config/modules/articles.php.
 * Les règles métier (ex. suppression interdite si l'objet est utilisé) vivent dans le contrôleur : `Gate::before`
 * contourne la policy pour le superadmin, pas la logique métier.
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.view');
    }

    public function view(User $user, Article $model): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function update(User $user, Article $model): bool
    {
        return $user->can('articles.update');
    }

    public function delete(User $user, Article $model): bool
    {
        return $user->can('articles.delete');
    }
}
