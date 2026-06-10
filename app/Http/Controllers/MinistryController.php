<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinistryController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/ministry
     * Returns the single ministry record with zone/church counts.
     */
    public function show(): JsonResponse
    {
        $ministry = Ministry::current()->loadCount(['zones', 'churches']);
        return $this->successResponse($ministry);
    }

    /**
     * PUT /api/ministry
     * Updates the single ministry's profile fields.
     * code is intentionally excluded — it cannot be changed after creation.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => ['sometimes', 'string', 'max:200'],
            'address'       => ['nullable', 'string'],
            'city'          => ['nullable', 'string', 'max:100'],
            'county'        => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'email'         => ['nullable', 'email'],
            'description'   => ['nullable', 'string'],
            'website'       => ['nullable', 'url'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'founded_year'  => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
        ]);

        $ministry = Ministry::current();
        $ministry->update($request->validated());

        return $this->successResponse($ministry->fresh(), 'Ministry profile updated.');
    }
}
