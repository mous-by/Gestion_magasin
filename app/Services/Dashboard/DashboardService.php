<?php

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * Point d'entrée du tableau de bord : données réelles uniquement, construites par RealDashboardData selon les
 * permissions de l'utilisateur (aucune donnée fictive).
 */
class DashboardService
{
    public function __construct(private readonly RealDashboardData $real) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return $this->real->for($user);
    }
}
