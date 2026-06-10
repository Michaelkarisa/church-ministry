<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name'      => ['sometimes', 'string', 'max:150'],
            'email'     => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'password'  => ['sometimes', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role_id'   => ['sometimes', 'exists:roles,id'],
            // ministry_id is immutable — always the single ministry
            'zone_id'   => ['nullable', 'exists:zones,id'],
            'church_id' => ['nullable', 'exists:churches,id'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }
}
