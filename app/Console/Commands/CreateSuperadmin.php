<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Services\Permissions\PermissionSynchronizer;
use App\Services\Users\UserProvisioningService;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Création d'un superadmin (D10, D26). Le mot de passe est saisi de façon interactive : jamais en argument (historique du
 * shell), jamais dans Git ni dans un seeder. Chaque développeur a son compte individuel (aucun compte partagé).
 * En développement, `php artisan db:seed` crée aussi les deux superadmins par défaut (D27, environnement local seulement).
 */
#[Signature('app:create-superadmin {--name= : Nom complet} {--phone= : Numéro de téléphone (identifiant de connexion)}')]
#[Description('Crée un compte superadmin (mot de passe saisi de façon interactive)')]
class CreateSuperadmin extends Command
{
    public function handle(UserProvisioningService $provisioning, PermissionSynchronizer $synchronizer): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Cette commande est interactive : le mot de passe ne se passe jamais en argument.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Nom complet');
        $phone = $this->option('phone') ?: $this->ask('Numéro de téléphone (identifiant de connexion, ex. 70 00 00 01)');
        $password = $this->secret('Mot de passe');
        $confirmation = $this->secret('Confirmer le mot de passe');

        $normalized = PhoneNumber::normalize($phone);

        $validator = Validator::make(
            ['name' => $name, 'phone' => $normalized, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:150'],
                'phone' => ['required', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
            ['phone.required' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).', 'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // Garantit que les rôles existent, même sur une base fraîchement migrée.
        $synchronizer->sync();

        ['user' => $user] = $provisioning->create(null, [
            'name' => $name,
            'phone' => $normalized,
            'password' => $password,
            'must_change_password' => false,
        ], RoleName::Superadmin);

        $this->components->info("Superadmin « {$user->name} » créé (#{$user->getKey()}) : connexion avec le numéro {$user->phone}.");

        return self::SUCCESS;
    }
}
