<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ResponsesController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return app()->version();
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/responses', [ResponsesController::class, 'index'])->name('responses');
});
