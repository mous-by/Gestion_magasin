<?php

use App\Http\Controllers\Web\Admin\AuditController;
use Illuminate\Support\Facades\Route;

// Journal d'audit : lecture seule, permission réservée au superadmin.
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/audit', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
});
