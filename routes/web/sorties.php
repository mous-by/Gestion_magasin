<?php

use App\Http\Controllers\Web\SortieController;
use Illuminate\Support\Facades\Route;

// Sorties (phase 5). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/sorties', [SortieController::class, 'index'])->middleware('permission:sorties.view')->name('sorties.index');
    Route::get('/sorties/create', [SortieController::class, 'create'])->middleware('permission:sorties.create')->name('sorties.create');
    Route::post('/sorties', [SortieController::class, 'store'])->middleware('permission:sorties.create')->name('sorties.store');
    Route::get('/sorties/{sortie}/edit', [SortieController::class, 'edit'])->middleware('permission:sorties.update')->name('sorties.edit');
    Route::put('/sorties/{sortie}', [SortieController::class, 'update'])->middleware('permission:sorties.update')->name('sorties.update');
    Route::delete('/sorties/{sortie}', [SortieController::class, 'destroy'])->middleware('permission:sorties.update')->name('sorties.destroy');
    Route::post('/sorties/{sortie}/validate', [SortieController::class, 'validateSortie'])->middleware('permission:sorties.validate')->name('sorties.validate');
    Route::post('/sorties/{sortie}/cancel', [SortieController::class, 'cancel'])->middleware('permission:sorties.cancel')->name('sorties.cancel');
    Route::get('/sorties/{sortie}/bon.pdf', [SortieController::class, 'pdf'])->middleware('permission:sorties.view')->name('sorties.pdf');
});
