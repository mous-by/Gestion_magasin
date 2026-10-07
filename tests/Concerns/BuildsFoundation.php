<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

trait BuildsFoundation
{
    /** Crée les rôles, les permissions des manifestes et les défauts par rôle (comme php artisan permissions:sync). */
    protected function syncPermissions(): void
    {
        Artisan::call('permissions:sync');
    }

    protected function superadmin(array $attributes = []): User
    {
        return User::factory()->superadmin()->create($attributes);
    }
}
