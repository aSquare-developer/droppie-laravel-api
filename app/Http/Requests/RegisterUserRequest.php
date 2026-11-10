<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Shared by the web and API registration so their rules cannot drift apart.
 */
class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::query()->whereEmail($value)->exists()) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'string', 'max:255', Password::min(6)],
        ];
    }
}
