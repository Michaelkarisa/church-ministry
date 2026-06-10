<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // ministry_id is always auto-assigned from Ministry::currentId()
            'name'      => ['required', 'string', 'max:150'],
            'code'      => ['required', 'string', 'max:20', 'unique:zones,code'],
            'region'    => ['nullable', 'string', 'max:100'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ];
    }
}
