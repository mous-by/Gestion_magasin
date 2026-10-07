<?php

namespace App\Enums;

enum ActivityChannel: string
{
    case Web = 'web';
    case Console = 'console';

    /**
     * Canal de la requête courante : console (hors tests), sinon Web.
     */
    public static function current(): self
    {
        return app()->runningInConsole() && ! app()->runningUnitTests() ? self::Console : self::Web;
    }
}
