<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EstaffWebhook extends FormRequest
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
        if ($this->input('event_type') === 'test') {
            return [
                'event_type' => 'required|string|in:test',
            ];
        }

        return [
            "event_type" => 'required|string',
            "data" => 'required|array',
            'data.state_id' => 'nullable|string',
            'data.vacancy_id' => 'nullable|integer',
            'data.candidate_id' => 'nullable|integer'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
