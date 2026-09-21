<?php

namespace App\Http\Requests\Flow;

use App\Http\Requests\BaseRequest;

/**
 * Twin voice webhook for the new flow (ТЗ 5.1). Any `event` is accepted with 200;
 * only CALL_ENDED is processed, so its fields are required conditionally.
 */
class CallEndedWebhook extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event' => 'required|string',
            'callTo' => 'required_if:event,CALL_ENDED|nullable|string',
            'botId' => 'required_if:event,CALL_ENDED|nullable|string',
            'status' => 'required_if:event,CALL_ENDED|nullable|string',
            'startedAt' => 'nullable|string',
            'callbackData' => 'nullable',
            'result' => 'nullable|array',
            'taskId' => 'nullable|string',
            'autoCallId' => 'nullable|string',
        ];
    }
}
