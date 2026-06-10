<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'church_id'       => ['required', 'exists:churches,id'],
            'member_number'   => ['required', 'string', 'max:30', 'unique:members,member_number'],
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'gender'          => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status'  => ['nullable', Rule::in(['single', 'married', 'widowed', 'divorced'])],
            'address'         => ['nullable', 'string', 'max:300'],
            'occupation'      => ['nullable', 'string', 'max:150'],
            'membership_date' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active'       => ['boolean'],
        ];
    }
}
