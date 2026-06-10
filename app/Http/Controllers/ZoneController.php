<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Models\Ministry;
use App\Models\Zone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    use ApiResponse;

    /** GET /api/zones */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Zone::withCount('churches')
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
            )
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        // ZoneAdmin sees only their zone
        if ($user->isZoneAdmin()) {
            $query->where('id', $user->zone_id);
        }

        return $this->successResponse($query->orderBy('name')->paginate($perPage));
    }

    /** POST /api/zones */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = Zone::create(array_merge(
            $request->validated(),
            ['ministry_id' => Ministry::currentId()]   // always the single ministry
        ));

        return $this->createdResponse($zone, 'Zone created successfully.');
    }

    /** GET /api/zones/{zone} */
    public function show(Request $request, Zone $zone): JsonResponse
    {
        $user = $request->user();

        if ($user->isZoneAdmin() && $user->zone_id !== $zone->id) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            $zone->load(['churches' => fn ($q) => $q->withCount([
                'members' => fn ($m) => $m->where('is_active', true),
            ])])
        );
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
            // ministry_id intentionally excluded — immutable
        ]);

        $zone->update($request->validated());
        return $this->successResponse($zone->fresh(), 'Zone updated successfully.');
    }

    /** DELETE /api/zones/{zone} */
    public function destroy(Zone $zone): JsonResponse
    {
        if ($zone->churches()->exists()) {
            return $this->errorResponse(
                'Cannot delete a zone with existing churches. Reassign or delete the churches first.',
                422
            );
        }

        $zone->delete();
        return $this->noContentResponse('Zone deleted successfully.');
    }
}
