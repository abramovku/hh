<?php

namespace App\Http\Requests\Flow;

use App\Http\Requests\BaseRequest;

/**
 * Twin voice webhook for the new and hybrid modes (ТЗ 5.1). Any `event` is accepted with 200;
 * CALL_ENDED fields are required conditionally. In hybrid mode CANDIDATE_CHANGED may still be
 * forwarded to the legacy job, which needs taskId (same rule as the legacy request).
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
            'taskId' => 'required_if:event,CANDIDATE_CHANGED|nullable|string',
            'autoCallId' => 'nullable|string',
        ];
    }
}
