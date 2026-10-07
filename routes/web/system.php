<?php

use App\Http\Controllers\Web\Admin\SystemController;
use Illuminate\Support\Facades\Route;

// Page Système : état en lecture seule et maintenance sur liste blanche. Permissions réservées au superadmin.
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/system', [SystemController::class, 'index'])->middleware('permission:system.view')->name('system.index');
    Route::post('/system/maintenance/{task}', [SystemController::class, 'maintain'])->middleware('permission:system.maintain')->where('task', '[a-z]+')->name('system.maintain');
});
