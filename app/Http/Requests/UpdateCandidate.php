<?php

namespace App\Http\Requests;

class UpdateCandidate extends BaseRequest
{
    public function rules(): array
    {
        return [
            'candidate' => 'required|array',
            'candidate.id' => 'required|integer',
            'changed_data' => 'required|array',
        ];
    }
}
