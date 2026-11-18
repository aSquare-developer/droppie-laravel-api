<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country_code'))) {
            $this->merge(['country_code' => strtoupper(trim($this->input('country_code'))) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['prohibited'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_registration_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'car_make_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_mileage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.9'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
