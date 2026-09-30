<?php

namespace App\Http\Controllers;

use App\Models\SubZone;
use App\Models\Zone;
use App\Services\SubZoneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubZoneController extends Controller
{
    use ApiResponse;

    public function __construct(private SubZoneService $subZoneService) {}

    /** GET /api/sub-zones — each admin sees only their own branch (see SubZoneService::index) */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->subZoneService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'zone_id'   => $request->zone_id,
                'region_id' => $request->region_id,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/sub-zones — zone admin and above (a sub-zone's parent level creates it) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'zone_id'   => ['required', 'exists:zones,id'],
            'name'      => ['required', 'string', 'max:150'],
            'code'      => ['required', 'string', 'max:20', 'unique:sub_zones,code'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        if (! $this->subZoneService->canManageWithinZone($user, $data['zone_id'])) {
            return $this->forbiddenResponse('You can only create sub-zones within your own zone or region.');
        }

        $subZone = $this->subZoneService->store($data);

        return $this->createdResponse($subZone, 'Sub-zone created successfully.');
    }

    /** GET /api/sub-zones/{subZone} */
    public function show(Request $request, SubZone $subZone): JsonResponse
    {
        if (! $this->subZoneService->canAccess($request->user(), $subZone)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->subZoneService->show($subZone));
    }

    /** PUT /api/sub-zones/{subZone} — its own admin may edit it, or anyone above them in its branch */
    public function update(Request $request, SubZone $subZone): JsonResponse
    {
        if (! $this->subZoneService->canAccess($request->user(), $subZone)) {
            return $this->forbiddenResponse();
        }

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:150'],
            'code'      => ['sometimes', 'string', 'max:20', 'unique:sub_zones,code,' . $subZone->id],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $subZone = $this->subZoneService->update($subZone, $data);

        return $this->successResponse($subZone, 'Sub-zone updated successfully.');
    }

    /** DELETE /api/sub-zones/{subZone} — zone admin and above only (not the sub-zone admin themselves) */
    public function destroy(Request $request, SubZone $subZone): JsonResponse
    {
        if (! $this->subZoneService->canManageWithinZone($request->user(), $subZone->zone_id)) {
            return $this->forbiddenResponse('Only the parent zone/region/ministry administrator can delete a sub-zone.');
        }

        try {
            $this->subZoneService->destroy($subZone);
            return $this->noContentResponse('Sub-zone deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
