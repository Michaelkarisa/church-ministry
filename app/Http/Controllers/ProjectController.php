<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Project;
use App\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(private ProjectService $projectService) {}

    /** GET /api/projects */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->projectService->index(
            $request->user(),
            [
                'church_id' => $request->church_id,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
                'search'    => $request->search,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/projects */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id'   => ['required', 'exists:churches,id'],
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['required', 'date'],
            // end_date is optional — duration is computed automatically either way.
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $user = $request->user();
        if ($user->isChurchAdmin() && $data['church_id'] !== $user->church_id) {
            return $this->forbiddenResponse('You can only create projects for your own church.');
        }
        if ($user->isZoneAdmin()) {
            $church = Church::find($data['church_id']);
            if (! $church || $church->zone_id !== $user->zone_id) {
                return $this->forbiddenResponse('That church is not in your zone.');
            }
        }

        $project = $this->projectService->store($user, $data);

        return $this->createdResponse($project, 'Project created successfully.');
    }

    /** GET /api/projects/{project} */
    public function show(Request $request, Project $project): JsonResponse
    {
        if (! $this->projectService->canAccess($request->user(), $project)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->projectService->show($project));
    }

    /** PUT /api/projects/{project} */
    public function update(Request $request, Project $project): JsonResponse
    {
        if (! $this->projectService->canAccess($request->user(), $project)) {
            return $this->forbiddenResponse();
        }

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['boolean'],
        ]);

        $project = $this->projectService->update($project, $data);

        return $this->successResponse($project, 'Project updated successfully.');
    }

    /** DELETE /api/projects/{project} */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        if (! $this->projectService->canAccess($request->user(), $project)) {
            return $this->forbiddenResponse();
        }

        $this->projectService->destroy($project);

        return $this->noContentResponse('Project deleted successfully.');
    }
}
