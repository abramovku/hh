<?php

namespace App\Http\Requests;

class GetVacancy extends BaseRequest
{
    public function rules(): array
    {
        return [
            'vacancy' => 'required|array',
            'vacancy.id' => 'required|integer|min:1',
        ];
    }
}
