<?php

use App\Http\Controllers\Web\PlannedModuleController;
use Illuminate\Support\Facades\Route;

// Fiches des modules métier à venir (D25). Pas de permission : c'est une maquette sans donnée ; la visibilité par rôle
// est décidée par PlannedModules (404 sinon).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/modules/{module}', PlannedModuleController::class)->name('modules.show');
});
