<?php

namespace App\Http\Middleware;

use App\Services\Users\SuperadminBootstrapper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de l'amorçage automatique (D27) : sur un hébergement Web sans accès console, la première visite de la page de
 * connexion crée les superadmins déclarés dans .env s'ils n'existent pas. Une requête indexée par visite au plus.
 */
class EnsureSuperadminsExist
{
    public function __construct(private readonly SuperadminBootstrapper $bootstrapper) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->bootstrapper->ensure();

        return $next($request);
    }
}
