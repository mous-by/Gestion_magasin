<?php

use App\Http\Controllers\Web\Admin\PermissionController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\RolePermissionController;
use App\Http\Controllers\Web\Admin\UserPermissionController;
use Illuminate\Support\Facades\Route;

// Rôles et permissions (D6, D23). Toutes ces permissions sont réservées au superadmin.
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('permission:permissions.assign')->name('roles.permissions.update');

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('permissions.store');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.create')->name('permissions.destroy');

    Route::get('/user-permissions', [UserPermissionController::class, 'index'])->middleware('permission:permissions.assign')->name('user-permissions.index');
    Route::get('/users/{user}/permissions', [UserPermissionController::class, 'show'])->middleware('permission:permissions.assign')->name('users.permissions.show');
    Route::put('/users/{user}/permissions', [UserPermissionController::class, 'update'])->middleware('permission:permissions.assign')->name('users.permissions.update');
});
