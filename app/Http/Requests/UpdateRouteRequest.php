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
            'start_place_id' => ['sometimes', 'required', 'string', 'max:255'],
            'end_place_id' => ['sometimes', 'required', 'string', 'max:255'],
            'start_address' => ['prohibited'],
            'end_address' => ['prohibited'],
            'start_address_session_token' => ['nullable', 'string', 'max:128'],
            'end_address_session_token' => ['nullable', 'string', 'max:128'],
            'started_at' => ['sometimes', 'date'],
            'distance_km' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_place_id.required' => 'Select an exact departure address from the suggestions.',
            'end_place_id.required' => 'Select an exact destination address from the suggestions.',
            'start_address.prohibited' => 'The departure address cannot be submitted as free-form text. Select an address from the suggestions.',
            'end_address.prohibited' => 'The destination address cannot be submitted as free-form text. Select an address from the suggestions.',
            'distance_km.prohibited' => 'Distance is calculated automatically.',
        ];
    }
}
