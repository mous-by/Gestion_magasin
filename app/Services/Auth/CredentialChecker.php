<?php

namespace App\Services\Auth;

use App\Enums\ActivityChannel;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vérification des identifiants de la connexion Web (CLAUDE.md §4.3).
 * L'identifiant est le numéro de téléphone (D26). 5 tentatives par numéro et par adresse IP ; échecs, blocages et
 * succès sont audités (le numéro tenté, jamais le mot de passe).
 */
class CredentialChecker
{
    public const MAX_ATTEMPTS = 5;

    private static ?string $dummyHash = null;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  'web'  $channel
     *
     * @throws ValidationException
     */
    public function attempt(string $phone, string $password, string $channel, string $ip): User
    {
        $normalized = PhoneNumber::normalize($phone);
        $attempted = $normalized ?? Str::limit(trim($phone), 30, '');
        $key = $this->throttleKey($attempted, $ip);

        $this->ensureNotRateLimited($key, $attempted);

        $user = $normalized === null ? null : User::withTrashed()->where('phone', $normalized)->first();

        // Toujours un contrôle de mot de passe, même pour un numéro inconnu : le temps de réponse ne révèle pas l'existence du compte.
        $passwordOk = Hash::check($password, $user?->password ?? self::dummyHash());

        if ($user === null || $user->trashed() || ! $passwordOk) {
            RateLimiter::hit($key);
            $this->logger->log('auth.login_failed', 'auth', "Échec de connexion — numéro « {$attempted} »", new: ['phone' => $attempted], channel: ActivityChannel::from($channel));

            throw ValidationException::withMessages(['phone' => trans('auth.failed')]);
        }

        // Identifiants exacts mais accès refusé : message précis, sans risque d'énumération (le mot de passe est juste).
        $reason = match (true) {
            $user->roleName() === null => ['no_role', 'Ce compte n\'a pas de rôle. Contactez votre administrateur.'],
            ! $user->canAuthenticate() => ['disabled', 'Ce compte est désactivé. Contactez votre administrateur.'],
            default => null,
        };

        if ($reason !== null) {
            RateLimiter::hit($key);
            $this->logger->log('auth.login_blocked', 'auth', "Connexion refusée ({$reason[0]}) — {$user->name}", $user, new: ['reason' => $reason[0]], actor: $user, channel: ActivityChannel::from($channel));

            throw ValidationException::withMessages(['phone' => $reason[1]]);
        }

        RateLimiter::clear($key);

        return $user;
    }

    /**
     * Enregistre la connexion réussie : dernière connexion et audit.
     */
    public function succeeded(User $user, string $channel): void
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->logger->log('auth.login', 'auth', "Connexion — {$user->name}", $user, actor: $user, channel: ActivityChannel::from($channel));
    }

    public function throttleKey(string $phone, string $ip): string
    {
        return Str::transliterate(Str::lower($phone).'|'.$ip);
    }

    private function ensureNotRateLimited(string $key, string $attempted): void
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);
        $this->logger->log('auth.lockout', 'auth', "Trop de tentatives — numéro « {$attempted} »", new: ['phone' => $attempted]);

        throw ValidationException::withMessages([
            'phone' => trans('auth.throttle', ['seconds' => $seconds]),
        ])->status(429);
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(24));
    }
}
