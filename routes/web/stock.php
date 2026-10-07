<?php

use App\Http\Controllers\Web\StockController;
use Illuminate\Support\Facades\Route;

// Stock et mouvements (phase 6). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock.view')->name('stock.index');
    Route::get('/stock/{article}', [StockController::class, 'show'])->middleware('permission:stock.view')->name('stock.show');
    Route::get('/stock/{article}/fiche.pdf', [StockController::class, 'pdf'])->middleware('permission:stock.view')->name('stock.pdf');
    Route::post('/stock/{article}/regularisations', [StockController::class, 'regularize'])->middleware('permission:stock.regularize')->name('stock.regularize');
});
