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

    /** GET /api/zones */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->zoneService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/zones */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = $this->zoneService->store($request->validated());

        return $this->createdResponse($zone, 'Zone created successfully.');
    }

    /** GET /api/zones/{zone} */
    public function show(Request $request, Zone $zone): JsonResponse
    {
        $user = $request->user();

        if ($user->isZoneAdmin() && $user->zone_id !== $zone->id) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->zoneService->show($zone));
    }

    /** PUT /api/zones/{zone} */
    public function update(Request $request, Zone $zone): JsonResponse
    {
        $request->validate([
            'name'      => ['sometimes', 'string', 'max:150'],
            'code'      => ['sometimes', 'string', 'max:20', 'unique:zones,code,' . $zone->id],
            'region'    => ['nullable', 'string', 'max:100'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $zone = $this->zoneService->update($zone, $request->validated());

        return $this->successResponse($zone, 'Zone updated successfully.');
    }

    /** DELETE /api/zones/{zone} */
    public function destroy(Zone $zone): JsonResponse
    {
        try {
            $this->zoneService->destroy($zone);
            return $this->noContentResponse('Zone deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
