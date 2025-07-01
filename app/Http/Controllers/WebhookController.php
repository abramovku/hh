<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function estaffWebhooks(Request $request)
    {
        Log::channel('estaff')->info("Webhook received", $request->all());

        //разбор хука если статус 32 то шлем в твин

        return response()->json('ok', 200);
    }

    public function twinWebhooks(Request $request)
    {
        Log::channel('twin')->info("Webhook received", $request->all());

        {
            "id": "00a54d39-a18b-45f6-959e-1a4cea82ff91",
          "bulkId": "a91f09a5-33f6-4a23-9f17-505a57956948",
          "groupId": "d9a74f70-7547-4603-bd5e-523f0a290c8c",
          "flowId": "5350e5f6-3215-4a0c-8869-dbeae14a6539",
          "oldStatus": "PENDING",
          "newStatus": "DELIVERED",
          "oldStatusCode": 2,
          "newStatusCode": 6,
          "price": 0,
          "partCount": 1,
          "callbackData": "\"id\":\"23233\""
        }

        return response()->json('ok', 200);
    }
}
