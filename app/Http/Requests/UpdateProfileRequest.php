<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['prohibited'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_registration_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'car_make_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_mileage' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
