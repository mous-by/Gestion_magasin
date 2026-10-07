<?php

namespace App\Exceptions;

use DomainException;

/**
 * Le dernier superadmin actif ne peut être ni désactivé, ni supprimé, ni changé de rôle (D10).
 */
class LastActiveSuperadminException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Le dernier superadmin actif ne peut pas être désactivé, supprimé ou changé de rôle.');
    }
}
