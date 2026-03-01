<?php

namespace App\Http\Requests;

class TwinVoiceWebhook extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event' => 'required|string|in:CANDIDATE_CHANGED',
            'taskId' => 'required|string',
        ];
    }
}
