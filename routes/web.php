<?php

use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\CallbackController;
use \App\Http\Controllers\WebhookController;
use \App\Http\Controllers\EndpointController;

Route::get('/', function () {
    return app()->version();
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);

