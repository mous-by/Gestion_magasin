<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Tâches planifiées (cron du serveur : `* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1`).
// Sauvegarde quotidienne puis vérification de l'archive (cahier §22) ; contrôle hebdomadaire de la cohérence du
// stock avec les mouvements (RG09).
Schedule::command('backup:run')->dailyAt(config('backup.time'))->withoutOverlapping()
    ->onSuccess(fn () => Artisan::call('backup:verify'));
Schedule::command('stock:verify')->weeklyOn(1, '06:00');
