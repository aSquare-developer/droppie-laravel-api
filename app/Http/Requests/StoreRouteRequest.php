<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteRequest extends FormRequest
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
            'start_address' => ['required', 'string', 'max:255'],
            'end_address' => ['required', 'string', 'max:255'],
            'distance_km' => ['required', 'integer', 'min:0'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
