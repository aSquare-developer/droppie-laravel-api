<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRoutesRequest extends FormRequest
{
    public const SORTS = [
        'created_at',
        '-created_at',
        'distance_km',
        '-distance_km',
        'started_at',
        '-started_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'min_distance' => ['nullable', 'numeric', 'min:0'],
            'max_distance' => array_values(array_filter([
                'nullable',
                'numeric',
                'min:0',
                is_numeric($this->input('min_distance')) ? 'gte:min_distance' : null,
            ])),
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
