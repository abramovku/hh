<?php

namespace App\Http\Requests;

class SetStateCandidate extends BaseRequest
{
    public function rules(): array
    {
        return [
            'candidate' => 'required|array',
            'candidate.id' => 'required|integer|min:1',
            'candidate.state_id' => 'required|string',
        ];
    }
}
