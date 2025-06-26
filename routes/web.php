<?php

use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\CallbackController;
use \App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);
Route::post('estaff-webhooks', [WebhookController::class, 'estaffWebhooks'])->name('estaff.webhook');
Route::get('estaff-webhooks', [WebhookController::class, 'estaffWebhooks'])->name('estaff.webhook');
