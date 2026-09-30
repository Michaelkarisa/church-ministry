<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    /**
     * Return a paginated, role-scoped list of projects.
     *
     * Accepted filters: church_id, is_active (bool|null), search
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::with('church')
            ->withCount('contributions')
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('title', 'like', "%{$v}%")
                  ->orWhere('description', 'like', "%{$v}%")
            );

        $query->whereHas('church', fn ($q) => $q->visibleTo($user));

        return $query->orderByDesc('start_date')->paginate($perPage);
    }

    public function store(User $user, array $data): Project
    {
        $data['created_by'] = $user->id;
        return Project::create($data)->load('church');
    }

    public function show(Project $project): Project
    {
        return $project->load(['church', 'creator', 'contributions.transactionType']);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh()->load('church');
    }

    public function destroy(Project $project): void
    {
        $project->delete();
    }

    public function canAccess(User $user, Project $project): bool
    {
        return $project->church && (new \App\Services\ChurchService())->canAccess($user, $project->church);
    }
}
