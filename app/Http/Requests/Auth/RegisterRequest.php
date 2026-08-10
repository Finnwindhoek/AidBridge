<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note there is no `role` rule: role is assigned by the controller, so mass
     * assignment cannot escalate a registration into an admin account.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()->min(8)->letters()->numbers()],

            // Malaysian NRIC: 12 digits, dashes optional.
            'nric' => ['required', 'string', 'regex:/^\d{6}-?\d{2}-?\d{4}$/'],

            'phone' => ['nullable', 'string', 'max:30'],
            'state' => ['nullable', 'string', 'max:60'],
            'is_disabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nric.regex' => 'The NRIC must be 12 digits, for example 900101-14-5566.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower((string) $this->input('email')),
            'nric' => str_replace([' ', '-'], '', (string) $this->input('nric')),
        ]);
    }
}
