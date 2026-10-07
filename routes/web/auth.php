<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordChangeController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;

// Authentification Web (session, guard `web`) : connexion par identifiant (D4), 5 tentatives (CredentialChecker).
// `superadmins.ensure` : crée les superadmins de .env s'ils n'existent pas (D27).
Route::middleware(['guest', 'superadmins.ensure'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Accessibles même quand le mot de passe est temporaire.
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });
});
