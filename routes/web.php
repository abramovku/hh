<?php

use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\CallbackController;
use \App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return "<?php echo app()->version(); ?>";
});

Route::get('hh-callback', [CallbackController::class, 'hhCallback']);
Route::post('estaff-webhooks', [WebhookController::class, 'estaffWebhooks'])->name('estaff.webhook');
Route::post('twin-webhooks', [WebhookController::class, 'twinWebhooks'])->name('twin.webhook');
