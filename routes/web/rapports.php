<?php

use App\Http\Controllers\Web\RapportController;
use Illuminate\Support\Facades\Route;

// Rapports (phase 7). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/rapports', [RapportController::class, 'index'])->middleware('permission:rapports.view')->name('rapports.index');
    Route::get('/rapports/{type}', [RapportController::class, 'show'])->middleware('permission:rapports.view')
        ->whereIn('type', ['etat-stock', 'entrees', 'sorties', 'ecarts'])->name('rapports.show');
});
