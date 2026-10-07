<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Permissions\PermissionSynchronizer;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Crée les superadmins déclarés dans .env (config/superadmins.php) s'ils n'existent pas (D27). Idempotent, sans effet si
 * rien n'est configuré, verrouillé contre les créations simultanées, silencieux si la base n'est pas encore prête.
 *
 * Déclenché par : le lancement de `artisan serve`, la fin de `migrate`, et la première visite de la page de connexion
 * (AppServiceProvider, EnsureSuperadminsExist). Hors environnement `local`, le compte doit changer son mot de passe à la
 * première connexion : celui du .env n'est alors que temporaire.
 */
class SuperadminBootstrapper
{
    /** Mots de passe notoirement faibles : leur usage hors développement est signalé dans les logs. */
    private const KNOWN_WEAK = ['admin123', 'password', 'password1', 'password123', '12345678', 'azerty123'];

    private bool $checked = false;

    public function __construct(private readonly PermissionSynchronizer $synchronizer, private readonly UserProvisioningService $provisioning) {}

    /**
     * @return list<string> numéros des comptes créés par cet appel
     */
    public function ensure(): array
    {
        if ($this->checked) {
            return [];
        }

        $accounts = $this->accounts();

        if (! config('superadmins.auto_create') || $accounts === [] || blank(config('superadmins.password'))) {
            $this->checked = true;

            return [];
        }

        try {
            if ($this->missing($accounts) === []) {
                $this->checked = true;

                return [];
            }

            $created = Cache::lock('superadmins.bootstrap', 15)->block(5, fn () => $this->create($this->missing($accounts)));
            $this->checked = true;

            return $created;
        } catch (LockTimeoutException) {
            return []; // un autre processus est en train de les créer
        } catch (QueryException $e) {
            Log::warning('Amorçage des superadmins impossible (base non migrée ?) : '.$e->getMessage());

            return [];
        }
    }

    /**
     * Comptes complets (nom + numéro valide), numéros normalisés.
     *
     * @return list<array{name: string, phone: string}>
     */
    private function accounts(): array
    {
        $accounts = [];

        foreach (config('superadmins.accounts', []) as $account) {
            $phone = PhoneNumber::normalize($account['phone'] ?? null);

            if (filled($account['name'] ?? null) && $phone !== null) {
                $accounts[] = ['name' => $account['name'], 'phone' => $phone];
            }
        }

        return $accounts;
    }

    /**
     * @param  list<array{name: string, phone: string}>  $accounts
     * @return list<array{name: string, phone: string}>
     */
    private function missing(array $accounts): array
    {
        $existing = User::withTrashed()->whereIn('phone', array_column($accounts, 'phone'))->pluck('phone')->all();

        return array_values(array_filter($accounts, fn ($account) => ! in_array($account['phone'], $existing, true)));
    }

    /**
     * @param  list<array{name: string, phone: string}>  $missing
     * @return list<string>
     */
    private function create(array $missing): array
    {
        if ($missing === []) {
            return [];
        }

        $password = (string) config('superadmins.password');
        $local = app()->environment('local');

        if (! $local && in_array(strtolower($password), self::KNOWN_WEAK, true)) {
            Log::warning('Superadmins créés avec un mot de passe notoirement faible hors développement : il devra être changé à la première connexion. Mettez un mot de passe fort dans le .env du serveur.');
        }

        $this->synchronizer->sync(); // les rôles doivent exister

        $created = [];

        foreach ($missing as $account) {
            try {
                $this->provisioning->create(null, [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'password' => $password,
                    // Hors développement, le mot de passe de .env n'est que temporaire.
                    'must_change_password' => ! $local,
                ], RoleName::Superadmin);

                $created[] = $account['phone'];
            } catch (ValidationException $e) {
                Log::warning("Superadmin {$account['name']} non créé : ".implode(' ', $e->validator->errors()->all()));
            }
        }

        return $created;
    }
}
