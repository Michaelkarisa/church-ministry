<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChurchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'zone_id'            => ['required', 'exists:zones,id'],
            'name'               => ['required', 'string', 'max:150'],
            'code'               => ['required', 'string', 'max:30', 'unique:churches,code'],
            'address'            => ['nullable', 'string'],
            'location'           => ['nullable', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'pastor_name'        => ['nullable', 'string', 'max:150'],
            'establishment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'is_active'          => ['boolean'],
        ];
    }
}
