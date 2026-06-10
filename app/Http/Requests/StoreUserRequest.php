<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role_id'   => ['required', 'exists:roles,id'],
            // ministry_id is always auto-assigned from Ministry::currentId()
            'zone_id'   => ['nullable', 'exists:zones,id'],
            'church_id' => ['nullable', 'exists:churches,id'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'A role must be assigned to every user.',
        ];
    }
}
