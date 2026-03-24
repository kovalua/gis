<?php

use App\Http\Controllers\Web\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin/gis')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('gis-admin.login');
        Route::post('/login', [LoginController::class, 'login'])->name('gis-admin.login.submit');
    });

    Route::middleware(['auth', 'gis.admin'])->group(function () {
        Route::view('/', 'gis-admin.index')->name('gis-admin.index');
        Route::post('/logout', [LoginController::class, 'logout'])->name('gis-admin.logout');
    });
});