<?php

namespace App\Http\Requests;

class FindCandidate extends BaseRequest
{
    public function rules(): array
    {
        return [
            'filter' => 'required|array',
        ];
    }
}
