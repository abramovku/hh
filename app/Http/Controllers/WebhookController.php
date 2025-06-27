<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function estaffWebhooks(Request $request)
    {
        Log::channel('estaff')->info("Webhook received", $request->all());
        return response()->json('ok', 200);
    }
}
