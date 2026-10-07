<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Logique de création et de sécurité des comptes, appelée par les contrôleurs et les commandes.
 * Applique le plafond de rôle. Un compte n'a jamais deux rôles.
 */
class UserProvisioningService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Crée un compte. Sans mot de passe fourni, un mot de passe temporaire est généré (il n'est retourné qu'ici)
     * et le compte devra le changer à la première connexion (D14).
     *
     * @param  array{name: string, phone: string, password?: ?string, is_active?: bool}  $data
     * @return array{user: User, temporary_password: ?string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(?User $actor, array $data, RoleName $role): array
    {
        $this->assertActorMayCreate($actor, $role);

        // D26 : le numéro est l'identifiant de connexion ; il est normalisé en E.164 avant validation et unicité.
        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null) ?? ($data['phone'] ?? null);

        $data = $this->validated($data, $role);

        $temporary = null;
        if (empty($data['password'])) {
            $temporary = $data['password'] = $this->generateTemporaryPassword();
            $data['must_change_password'] = true;
        }

        $user = DB::transaction(function () use ($actor, $data, $role) {
            $user = User::create($data);
            $user->syncRoles([$role->value]);

            $this->logger->log(
                'users.role_assigned',
                'users',
                "Rôle attribué — {$user->name} : {$role->label()}",
                $user,
                new: ['role' => $role->value],
                actor: $actor,
            );

            return $user;
        });

        return ['user' => $user, 'temporary_password' => $temporary];
    }

    /**
     * Modification par l'utilisateur de ses propres informations (nom, numéro). Rien d'autre : rôle et
     * statut ne passent jamais par ici. L'audit (`users.updated`, anciennes et nouvelles valeurs) est écrit par le modèle.
     *
     * @param  array{name: string, phone: string}  $data
     * @return bool vrai si quelque chose a changé
     */
    public function updateProfile(User $user, array $data): bool
    {
        $user->fill(['name' => $data['name'], 'phone' => $data['phone']]);

        if (! $user->isDirty()) {
            return false;
        }

        $user->save();

        return true;
    }

    /**
     * Modification d'un compte par un supérieur (plafond de rôle) : nom et numéro toujours ; statut seulement si le
     * contrôleur l'inclut dans $data (réservé à `users.activate` — ce service ne fait qu'appliquer et valider ce qu'il reçoit).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateByAdmin(User $actor, User $target, array $data): void
    {
        if (! $actor->isSuperadmin() && ! ($actor->roleName()?->canManage($target->roleName() ?? RoleName::Superadmin) ?? false)) {
            throw new AuthorizationException('Vous ne pouvez pas modifier ce compte.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')->ignore($target->getKey())],
        ];

        if (array_key_exists('is_active', $data)) {
            $rules['is_active'] = ['boolean'];
        }

        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null) ?? ($data['phone'] ?? null);

        $validated = Validator::make($data, $rules, [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).',
        ])->validate();

        $target->update($validated);
    }

    /**
     * Changement de mot de passe par l'utilisateur lui-même : lève l'obligation de changement, coupe les autres sessions.
     */
    public function changePassword(User $user, string $newPassword, ?string $currentSessionId = null): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentSessionId) {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            $this->revokeAccess($user, $currentSessionId);

            $this->logger->log('auth.password_changed', 'auth', "Mot de passe modifié — {$user->name}", $user, actor: $user);
        });
    }

    /**
     * Réinitialisation par un supérieur : nouveau mot de passe temporaire, changement obligatoire, accès coupés.
     *
     * @return string le mot de passe temporaire (affiché une seule fois à l'auteur)
     */
    public function resetPassword(User $actor, User $target): string
    {
        if (! $actor->isSuperadmin() && ! ($actor->roleName()?->canManage($target->roleName() ?? RoleName::Superadmin) ?? false)) {
            throw new AuthorizationException('Vous ne pouvez pas réinitialiser ce compte.');
        }

        $temporary = $this->generateTemporaryPassword();

        DB::transaction(function () use ($actor, $target, $temporary) {
            $target->forceFill(['password' => $temporary, 'must_change_password' => true, 'password_changed_at' => null])->save();
            $this->revokeAccess($target);
            $this->logger->log('auth.password_reset', 'auth', "Mot de passe réinitialisé — {$target->name}", $target, actor: $actor);
        });

        return $temporary;
    }

    /**
     * Supprime les sessions Web de l'utilisateur (sauf éventuellement la courante).
     */
    public function revokeAccess(User $user, ?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    /**
     * Mot de passe temporaire conforme à D14 : 12 caractères, lettres et chiffres, sans caractères ambigus.
     */
    public function generateTemporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $digits = '23456789';
        $pool = $letters.$digits;

        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        for ($i = count($chars); $i < 12; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        return Str::of(implode('', collect($chars)->shuffle()->all()))->toString();
    }

    private function assertActorMayCreate(?User $actor, RoleName $role): void
    {
        // Sans acteur : console (commande Artisan de création du superadmin).
        if ($actor === null || $actor->isSuperadmin()) {
            return;
        }

        if (! ($actor->roleName()?->canManage($role) ?? false)) {
            throw new AuthorizationException('Votre rôle ne permet pas de créer ce type de compte.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(array $data, RoleName $role): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            // Identifiant de connexion (D26) : obligatoire, E.164, unique y compris parmi les comptes supprimés.
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).',
        ]);

        $validated = $validator->validate();

        return array_merge($validated, array_filter(['is_active' => $data['is_active'] ?? true, 'must_change_password' => $data['must_change_password'] ?? false], fn ($v) => $v !== null));
    }
}
