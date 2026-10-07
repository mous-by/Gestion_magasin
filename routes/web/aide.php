<?php

use Illuminate\Support\Facades\Route;

// Guide d'utilisation (documentation utilisateur, cahier §30, §31). Page statique, sans donnée.
Route::middleware(['auth', 'active', 'password.changed', 'permission:aide.view'])->group(function () {
    Route::view('/aide', 'aide.index')->name('aide.index');
});
