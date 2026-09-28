<?php

namespace App\Http\Controllers;

use App\Models\SubZone;
use App\Services\SubZoneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubZoneController extends Controller
{
    use ApiResponse;

    public function __construct(private SubZoneService $subZoneService) {}

    /** GET /api/sub-zones */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->subZoneService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'zone_id'   => $request->zone_id,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/sub-zones */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isMinistryAdmin() && ! $user->isZoneAdmin()) {
            return $this->forbiddenResponse('Only Ministry or Zone Administrators can create sub-zones.');
        }

        $data = $request->validate([
            'zone_id'   => ['required', 'exists:zones,id'],
            'name'      => ['required', 'string', 'max:150'],
            'code'      => ['required', 'string', 'max:20', 'unique:sub_zones,code'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        if ($user->isZoneAdmin() && $data['zone_id'] !== $user->zone_id) {
            return $this->forbiddenResponse('You can only create sub-zones within your own zone.');
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

    /** PUT /api/sub-zones/{subZone} */
    public function update(Request $request, SubZone $subZone): JsonResponse
    {
        $user = $request->user();

        if (! $user->isMinistryAdmin() && ! ($user->isZoneAdmin() && $subZone->zone_id === $user->zone_id)) {
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

    /** DELETE /api/sub-zones/{subZone} */
    public function destroy(Request $request, SubZone $subZone): JsonResponse
    {
        $user = $request->user();

        if (! $user->isMinistryAdmin() && ! ($user->isZoneAdmin() && $subZone->zone_id === $user->zone_id)) {
            return $this->forbiddenResponse();
        }

        try {
            $this->subZoneService->destroy($subZone);
            return $this->noContentResponse('Sub-zone deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
