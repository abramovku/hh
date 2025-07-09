<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetStateCandidate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function expectsJson(): bool
    {
        return true;
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
            "candidate.id" => 'required|integer',
            "candidate.state_id" => 'required|string',
        ];
    }
}
