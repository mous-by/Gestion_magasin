<?php

use App\Http\Controllers\Web\Admin\DocumentSettingsController;
use Illuminate\Support\Facades\Route;

// Paramètres des documents imprimés. Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed', 'permission:documents.manage'])->group(function () {
    Route::get('/parametres/documents', [DocumentSettingsController::class, 'edit'])->name('documents.settings');
    Route::put('/parametres/documents', [DocumentSettingsController::class, 'update'])->name('documents.settings.update');
    Route::post('/parametres/documents/logo', [DocumentSettingsController::class, 'updateLogo'])->name('documents.settings.logo');
    Route::delete('/parametres/documents/logo', [DocumentSettingsController::class, 'removeLogo'])->name('documents.settings.logo.remove');
    Route::get('/parametres/documents/apercu', [DocumentSettingsController::class, 'preview'])->name('documents.settings.preview');
});
