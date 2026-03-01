<?php

namespace App\Http\Requests;

class GetCandidate extends BaseRequest
{
    public function rules(): array
    {
        return [
            'candidate' => 'required|array',
            'candidate.id' => 'required|integer|min:1',
        ];
    }
}
