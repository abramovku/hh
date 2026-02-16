<?php

use App\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return app()->version();
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);
