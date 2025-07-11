<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\WebhookController;
use \App\Http\Controllers\EndpointController;

Route::post('estaff-webhooks', [WebhookController::class, 'estaffWebhooks'])->name('estaff.webhook');
Route::post('twin-webhooks', [WebhookController::class, 'twinWebhooks'])->name('twin.webhook');
Route::group(['as' => 'twin.', 'prefix' => 'twin'], function () {
    Route::post('createCandidate', [EndpointController::class, 'create'])->name('twin.create');
    Route::post('updateCandidate', [EndpointController::class, 'update'])->name('twin.update');
    Route::post('stateCandidate', [EndpointController::class, 'state'])->name('twin.state');
});
