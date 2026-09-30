<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Models\Zone;
use App\Services\ZoneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    use ApiResponse;

    public function __construct(private ZoneService $zoneService) {}

    /** GET /api/zones — each admin sees only their own branch (see ZoneService::index) */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->zoneService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'region_id' => $request->region_id,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/zones — region admin and above (a zone's parent level creates it) */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        if (! $this->zoneService->canManageWithinRegion($request->user(), $request->region_id)) {
            return $this->forbiddenResponse('You can only create zones within your own region.');
        }

        $zone = $this->zoneService->store($request->validated());

        return $this->createdResponse($zone, 'Zone created successfully.');
    }

    /** GET /api/zones/{zone} */
    public function show(Request $request, Zone $zone): JsonResponse
    {
        if (! $this->zoneService->canAccess($request->user(), $zone)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->zoneService->show($zone));
    }

    /** PUT /api/zones/{zone} — its own admin may edit it, or anyone above them in its branch */
    public function update(Request $request, Zone $zone): JsonResponse
    {
        if (! $this->zoneService->canManage($request->user(), $zone)) {
            return $this->forbiddenResponse();
        }

        $request->validate([
            'name'      => ['sometimes', 'string', 'max:150'],
            'code'      => ['sometimes', 'string', 'max:20', 'unique:zones,code,' . $zone->id],
            'region_id' => ['sometimes', 'exists:regions,id'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $zone = $this->zoneService->update($zone, $request->validated());

        return $this->successResponse($zone, 'Zone updated successfully.');
    }

    /** DELETE /api/zones/{zone} — region admin and above only (not the zone admin themselves) */
    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        if (! $this->zoneService->canManageWithinRegion($request->user(), $zone->region_id)) {
            return $this->forbiddenResponse('Only the parent region/ministry administrator can delete a zone.');
        }

        try {
            $this->zoneService->destroy($zone);
            return $this->noContentResponse('Zone deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
