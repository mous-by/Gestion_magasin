<?php

use App\Http\Controllers\Web\Referentiels\FournisseurController;
use Illuminate\Support\Facades\Route;

// Fournisseurs (phase 3). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/fournisseurs', [FournisseurController::class, 'index'])->middleware('permission:fournisseurs.view')->name('fournisseurs.index');
    Route::post('/fournisseurs', [FournisseurController::class, 'store'])->middleware('permission:fournisseurs.create')->name('fournisseurs.store');
    Route::put('/fournisseurs/{fournisseur}', [FournisseurController::class, 'update'])->middleware('permission:fournisseurs.update')->name('fournisseurs.update');
    Route::delete('/fournisseurs/{fournisseur}', [FournisseurController::class, 'destroy'])->middleware('permission:fournisseurs.delete')->name('fournisseurs.destroy');
});
