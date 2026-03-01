<?php

namespace App\Http\Requests;

class EstaffWebhook extends BaseRequest
{
    public function rules(): array
    {
        if ($this->input('event_type') === 'test') {
            return [
                'event_type' => 'required|string|in:test',
            ];
        }

        return [
            'event_type' => 'required|string',
            'data' => 'required|array',
            'data.state_id' => 'nullable|string',
            'data.vacancy_id' => 'nullable|integer',
            'data.candidate_id' => 'nullable|integer',
        ];
    }
}
