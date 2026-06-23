<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchRequest;
use App\Models\Church;
use App\Services\ChurchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchController extends Controller
{
    use ApiResponse;

    public function __construct(private ChurchService $churchService) {}

    /** GET /api/churches */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->churchService->index(
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

    /** POST /api/churches */
    public function store(StoreChurchRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isZoneAdmin() && $request->zone_id !== $user->zone_id) {
            return $this->forbiddenResponse('You can only create churches within your zone.');
        }

        $church = $this->churchService->store($request->validated());

        return $this->createdResponse($church->load('zone'), 'Church created successfully.');
    }

    /** GET /api/churches/{church} */
    public function show(Request $request, Church $church): JsonResponse
    {
        if (! $this->churchService->canAccess($request->user(), $church)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->churchService->show($church));
    }

    /** PUT /api/churches/{church} */
    public function update(Request $request, Church $church): JsonResponse
    {
        if (! $this->churchService->canAccess($request->user(), $church)) {
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

        $church = $this->churchService->update($church, $request->validated());

        return $this->successResponse($church, 'Church updated successfully.');
    }

    /** DELETE /api/churches/{church} */
    public function destroy(Church $church): JsonResponse
    {
        try {
            $this->churchService->destroy($church);
            return $this->noContentResponse('Church deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
