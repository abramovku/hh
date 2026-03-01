<?php

namespace App\Http\Requests;

class TwinTextWebhook extends BaseRequest
{
    public function rules(): array
    {
        return [
            'newStatus' => 'required|string',
            'callbackData' => 'required|string',
            'id' => 'required|string',
        ];
    }
}
