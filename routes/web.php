<?php

use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\CallbackController;

Route::get('/', function () {
    return app()->version();
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);

