<?php

use App\Http\Controllers\Web\EntreeController;
use Illuminate\Support\Facades\Route;

// Entrées / réceptions (phase 4). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/entrees', [EntreeController::class, 'index'])->middleware('permission:entrees.view')->name('entrees.index');
    Route::get('/entrees/create', [EntreeController::class, 'create'])->middleware('permission:entrees.create')->name('entrees.create');
    Route::post('/entrees', [EntreeController::class, 'store'])->middleware('permission:entrees.create')->name('entrees.store');
    Route::get('/entrees/{entree}/edit', [EntreeController::class, 'edit'])->middleware('permission:entrees.update')->name('entrees.edit');
    Route::put('/entrees/{entree}', [EntreeController::class, 'update'])->middleware('permission:entrees.update')->name('entrees.update');
    Route::delete('/entrees/{entree}', [EntreeController::class, 'destroy'])->middleware('permission:entrees.update')->name('entrees.destroy');
    Route::post('/entrees/{entree}/validate', [EntreeController::class, 'validateEntree'])->middleware('permission:entrees.validate')->name('entrees.validate');
    Route::post('/entrees/{entree}/resolve', [EntreeController::class, 'resolve'])->middleware('permission:entrees.resolve')->name('entrees.resolve');
    Route::post('/entrees/{entree}/cancel', [EntreeController::class, 'cancel'])->middleware('permission:entrees.cancel')->name('entrees.cancel');
    Route::get('/entrees/{entree}/fiche.pdf', [EntreeController::class, 'pdf'])->middleware('permission:entrees.view')->name('entrees.pdf');
});
