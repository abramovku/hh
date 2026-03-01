<?php

namespace App\Http\Requests;

class AddCandidate extends BaseRequest
{
    public function rules(): array
    {
        return [
            'candidate' => 'required|array',
            'candidate.firstname' => 'required|string',
            'candidate.mobile_phone' => 'required|string',
            'candidate.email' => 'required|string',
            'vacancy' => 'required|array',
            'vacancy.id' => 'required|integer|min:1',
        ];
    }
}
