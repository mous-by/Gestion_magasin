<?php

use App\Http\Controllers\Web\Referentiels\ServiceController;
use Illuminate\Support\Facades\Route;

// Services bénéficiaires des sorties (phase 3). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:services.view')->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:services.create')->name('services.store');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:services.update')->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->middleware('permission:services.delete')->name('services.destroy');
});
