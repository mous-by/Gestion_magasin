<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Hash aléatoire, partagé par les utilisateurs d'une exécution : aucun mot de passe connu n'existe hors d'un test qui le fixe. */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+2237'.fake()->unique()->numerify('#######'),
            'password' => static::$password ??= Hash::make(Str::random(32)),
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            Role::findOrCreate($role->value, 'web');
            $user->syncRoles([$role->value]);
        });
    }

    public function superadmin(): static
    {
        return $this->withRole(RoleName::Superadmin);
    }

    public function administrateur(): static
    {
        return $this->withRole(RoleName::Administrateur);
    }

    public function magasinier(): static
    {
        return $this->withRole(RoleName::Magasinier);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }
}
