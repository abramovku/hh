<?php

use App\Http\Controllers\EndpointController;
use App\Http\Controllers\FlowSwitchController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('health/estaff', [HealthController::class, 'estaff'])->name('health.estaff');

// FlowSwitchController forwards to WebhookController (legacy) or FlowWebhookController (FLOW_MODE=new).
Route::post('estaff-webhooks', [FlowSwitchController::class, 'estaffWebhooks'])->name('estaff.webhook');
Route::post('twin-webhooks', [WebhookController::class, 'twinWebhooks'])->name('twin.webhook');
Route::post('twin-webhooks-voice', [FlowSwitchController::class, 'twinVoiceWebhooks'])->name('twin.webhook.voice');
Route::group(['as' => 'twin.', 'prefix' => 'twin'], function () {
    Route::post('createCandidate', [EndpointController::class, 'create'])->name('twin.create');
    Route::post('updateCandidate', [EndpointController::class, 'update'])->name('twin.update');
    Route::post('stateCandidate', [EndpointController::class, 'state'])->name('twin.state');
    Route::post('eventCandidate', [EndpointController::class, 'event'])->name('twin.event');
    Route::post('getCandidate', [EndpointController::class, 'get'])->name('twin.get');
    Route::post('findCandidate', [EndpointController::class, 'find'])->name('twin.find');
    Route::post('findVacancy', [EndpointController::class, 'findVacancy'])->name('twin.findVacancy');
    Route::post('getVacancy', [EndpointController::class, 'getVacancy'])->name('twin.getVacancy');
});
