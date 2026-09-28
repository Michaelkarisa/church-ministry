<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\RegionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Accessible by: MinistryAdmin (create/update/delete). Read access is
// broader — any authenticated admin can view the region list, since
// it's structural, not financial, information.
class RegionController extends Controller
{
    use ApiResponse;

    public function __construct(private RegionService $regionService) {}

    /** GET /api/regions */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->regionService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/regions */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Only Ministry Administrators can create regions.');
        }

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'code'      => ['required', 'string', 'max:20', 'unique:regions,code'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $region = $this->regionService->store($data);

        return $this->createdResponse($region, 'Region created successfully.');
    }

    /** GET /api/regions/{region} */
    public function show(Region $region): JsonResponse
    {
        return $this->successResponse($this->regionService->show($region));
    }

    /** PUT /api/regions/{region} */
    public function update(Request $request, Region $region): JsonResponse
    {
        if (! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Only Ministry Administrators can update regions.');
        }

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:150'],
            'code'      => ['sometimes', 'string', 'max:20', 'unique:regions,code,' . $region->id],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $region = $this->regionService->update($region, $data);

        return $this->successResponse($region, 'Region updated successfully.');
    }

    /** DELETE /api/regions/{region} */
    public function destroy(Request $request, Region $region): JsonResponse
    {
        if (! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Only Ministry Administrators can delete regions.');
        }

        try {
            $this->regionService->destroy($region);
            return $this->noContentResponse('Region deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
