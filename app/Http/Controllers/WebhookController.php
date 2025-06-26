<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function estaffWebhooks(Request $request)
    {
        return response()->json('ok', 200);
    }
}
