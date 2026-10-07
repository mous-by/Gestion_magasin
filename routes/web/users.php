<?php

use App\Http\Controllers\Web\Admin\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Les comptes superadmin sont invisibles pour les autres rôles, qui ne doivent pas même connaître leur existence :
// une adresse qui en désigne un répond « introuvable » (404), comme un compte inexistant, et non « accès refusé ».
Route::bind('user', function (string $value) {
    $user = User::findOrFail($value);
    abort_if($user->isSuperadmin() && ! Auth::user()?->isSuperadmin(), 404);

    return $user;
});

// Utilisateurs. Toutes les routes portent permission:module.action (jamais role:, CLAUDE.md §4.4).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset_password')->name('users.reset-password');
    Route::post('/users/{user}/revoke-access', [UserController::class, 'revokeAccess'])->middleware('permission:users.revoke_access')->name('users.revoke-access');
});
