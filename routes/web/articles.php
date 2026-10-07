<?php

use App\Http\Controllers\Web\Referentiels\ArticleController;
use App\Http\Controllers\Web\Referentiels\ArticleImportController;
use App\Http\Controllers\Web\Referentiels\CategoryController;
use App\Http\Controllers\Web\Referentiels\UniteController;
use Illuminate\Support\Facades\Route;

// Articles, catégories et unités (phase 3). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/articles', [ArticleController::class, 'index'])->middleware('permission:articles.view')->name('articles.index');
    Route::get('/articles/search', [ArticleController::class, 'search'])->middleware('permission:articles.view')->name('articles.search');
    Route::get('/articles/import/modele', [ArticleImportController::class, 'template'])->middleware('permission:articles.import')->name('articles.import.template');
    Route::post('/articles/import', [ArticleImportController::class, 'store'])->middleware('permission:articles.import')->name('articles.import');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware('permission:articles.create')->name('articles.store');
    Route::put('/articles/{article}', [ArticleController::class, 'update'])->middleware('permission:articles.update')->name('articles.update');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->middleware('permission:articles.delete')->name('articles.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:articles.manage_referentiels')->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:articles.manage_referentiels')->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:articles.manage_referentiels')->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:articles.manage_referentiels')->name('categories.destroy');

    Route::get('/unites', [UniteController::class, 'index'])->middleware('permission:articles.manage_referentiels')->name('unites.index');
    Route::post('/unites', [UniteController::class, 'store'])->middleware('permission:articles.manage_referentiels')->name('unites.store');
    Route::put('/unites/{unite}', [UniteController::class, 'update'])->middleware('permission:articles.manage_referentiels')->name('unites.update');
    Route::delete('/unites/{unite}', [UniteController::class, 'destroy'])->middleware('permission:articles.manage_referentiels')->name('unites.destroy');
});
