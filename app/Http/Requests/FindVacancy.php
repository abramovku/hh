<?php

namespace App\Http\Requests;

class FindVacancy extends BaseRequest
{
    public function rules(): array
    {
        return [
            'filter' => 'required|array',
        ];
    }
}
