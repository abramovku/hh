<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCandidate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "candidate" => 'required|array',
            "candidate.*.firstname" => 'required|string',
            "candidate.*.mobile_phone" => 'required|string',
            "candidate.*.email" => 'required|string',
            "vacancy" => 'required|array',
            "vacancy.*.id" => 'required|integer',
        ];
    }
}
