<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChurchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Churches now belong to a Sub-zone (Ministry -> Region -> Zone -> Sub-zone -> Church)
            'sub_zone_id'        => ['required', 'exists:sub_zones,id'],
            'name'               => ['required', 'string', 'max:150'],
            'code'               => ['required', 'string', 'max:30', 'unique:churches,code'],
            'address'            => ['nullable', 'string'],
            'location'           => ['nullable', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'establishment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            // Assets — pastor_name removed; leadership is now managed
            // separately via the Leadership table/endpoints.
            'land_status'        => ['nullable', 'in:rented,bought'],
            'building_status'    => ['nullable', 'in:rented,built,under_construction'],
            'is_active'          => ['boolean'],
        ];
    }
}
