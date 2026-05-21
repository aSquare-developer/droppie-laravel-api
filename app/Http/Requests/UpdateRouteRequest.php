<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_address' => ['sometimes', 'string', 'max:255'],
            'end_address' => ['sometimes', 'string', 'max:255'],
            'distance_km' => ['sometimes', 'integer', 'min:0'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
