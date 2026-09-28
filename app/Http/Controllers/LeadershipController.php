<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Leadership;
use App\Services\LeadershipService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadershipController extends Controller
{
    use ApiResponse;

    public function __construct(private LeadershipService $leadershipService) {}

    /** GET /api/leadership */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->leadershipService->index(
            $request->user(),
            [
                'church_id' => $request->church_id,
                'role'      => $request->role,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** GET /api/churches/{church}/leadership — leaders of one specific church */
    public function forChurch(Request $request, Church $church): JsonResponse
    {
        $leaders = $this->leadershipService->forChurch($church, ! $request->boolean('include_inactive'));

        return $this->successResponse($leaders);
    }

    /** POST /api/leadership */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id'  => ['required', 'exists:churches,id'],
            'name'       => ['required', 'string', 'max:150'],
            // Free-text role (Pastor, Assistant Pastor, Elder, etc.)
            'role'       => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'email'      => ['nullable', 'email'],
            'is_primary' => ['boolean'],
            'is_active'  => ['boolean'],
        ]);

        $user = $request->user();
        if ($user->isChurchAdmin() && $data['church_id'] !== $user->church_id) {
            return $this->forbiddenResponse('You can only add leaders for your own church.');
        }
        if ($user->isZoneAdmin()) {
            $church = Church::find($data['church_id']);
            if (! $church || $church->zone_id !== $user->zone_id) {
                return $this->forbiddenResponse('That church is not in your zone.');
            }
        }

        $leadership = $this->leadershipService->store($data);

        return $this->createdResponse($leadership, 'Leader added successfully.');
    }

    /** PUT /api/leadership/{leadership} */
    public function update(Request $request, Leadership $leadership): JsonResponse
    {
        if (! $this->leadershipService->canAccess($request->user(), $leadership)) {
            return $this->forbiddenResponse();
        }

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:150'],
            'role'       => ['sometimes', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'email'      => ['nullable', 'email'],
            'is_primary' => ['boolean'],
            'is_active'  => ['boolean'],
        ]);

        $leadership = $this->leadershipService->update($leadership, $data);

        return $this->successResponse($leadership, 'Leader updated successfully.');
    }

    /** DELETE /api/leadership/{leadership} */
    public function destroy(Request $request, Leadership $leadership): JsonResponse
    {
        if (! $this->leadershipService->canAccess($request->user(), $leadership)) {
            return $this->forbiddenResponse();
        }

        $this->leadershipService->destroy($leadership);

        return $this->noContentResponse('Leader removed successfully.');
    }
}
