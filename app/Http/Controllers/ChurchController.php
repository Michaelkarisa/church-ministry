<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchRequest;
use App\Models\Church;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchController extends Controller
{
    use ApiResponse;

    /** GET /api/churches */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Church::with('zone')
            ->withCount(['members' => fn ($q) => $q->where('is_active', true)])
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
            )
            ->when($request->zone_id, fn ($q) => $q->where('zone_id', $request->zone_id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        // Scope by role
        if ($user->isZoneAdmin()) {
            $query->where('zone_id', $user->zone_id);
        } elseif ($user->isChurchAdmin()) {
            $query->where('id', $user->church_id);
        }

        return $this->successResponse($query->orderBy('name')->paginate($perPage));
    }

    /** POST /api/churches */
    public function store(StoreChurchRequest $request): JsonResponse
    {
        $user = $request->user();

        // ZoneAdmin can only create churches in their zone
        if ($user->isZoneAdmin() && $request->zone_id !== $user->zone_id) {
            return $this->forbiddenResponse('You can only create churches within your zone.');
        }

        $church = Church::create($request->validated());
        return $this->createdResponse($church->load('zone'), 'Church created successfully.');
    }

    /** GET /api/churches/{church} */
    public function show(Request $request, Church $church): JsonResponse
    {
        $user = $request->user();

        if ($user->isZoneAdmin() && $church->zone_id !== $user->zone_id) {
            return $this->forbiddenResponse();
        }
        if ($user->isChurchAdmin() && $church->id !== $user->church_id) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            $church->load(['zone.ministry'])->loadCount(['members' => fn ($q) => $q->where('is_active', true)])
        );
    }

    /** PUT /api/churches/{church} */
    public function update(Request $request, Church $church): JsonResponse
    {
        $user = $request->user();

        if ($user->isZoneAdmin() && $church->zone_id !== $user->zone_id) {
            return $this->forbiddenResponse();
        }
        if ($user->isChurchAdmin() && $church->id !== $user->church_id) {
            return $this->forbiddenResponse();
        }

        $request->validate([
            'name'               => ['sometimes', 'string', 'max:150'],
            'code'               => ['sometimes', 'string', 'max:30', 'unique:churches,code,' . $church->id],
            'address'            => ['nullable', 'string'],
            'location'           => ['nullable', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'pastor_name'        => ['nullable', 'string', 'max:150'],
            'establishment_date' => ['nullable', 'date'],
            'latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'is_active'          => ['boolean'],
        ]);

        $church->update($request->validated());
        return $this->successResponse($church->fresh()->load('zone'), 'Church updated successfully.');
    }

    /** DELETE /api/churches/{church} */
    public function destroy(Church $church): JsonResponse
    {
        if ($church->members()->exists() || $church->transactions()->exists()) {
            return $this->errorResponse(
                'Cannot delete a church with existing members or financial records.',
                422
            );
        }

        $church->delete();
        return $this->noContentResponse('Church deleted successfully.');
    }
}
