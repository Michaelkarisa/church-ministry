<?php

namespace App\Http\Controllers;

use App\Services\MinistryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinistryController extends Controller
{
    use ApiResponse;

    public function __construct(private MinistryService $ministryService) {}

    /**
     * GET /api/ministry
     */
    public function show(): JsonResponse
    {
        return $this->successResponse($this->ministryService->getCurrent());
    }

    /**
     * PUT /api/ministry
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

        $ministry = $this->ministryService->update($request->validated());

        return $this->successResponse($ministry, 'Ministry profile updated.');
    }
}
