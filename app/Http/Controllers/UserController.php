<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Ministry;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    /** GET /api/users */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $users = User::with('role')
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->role_id,   fn ($q) => $q->where('role_id',   $request->role_id))
            ->when($request->zone_id,   fn ($q) => $q->where('zone_id',   $request->zone_id))
            ->when($request->church_id, fn ($q) => $q->where('church_id', $request->church_id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($perPage);

        return $this->successResponse($users);
    }

    /** POST /api/users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Always attach the single ministry — callers never supply this
        $data['ministry_id'] = Ministry::currentId();

        $user = User::create($data);
        return $this->createdResponse($user->load('role'), 'User created successfully.');
    }

    /** GET /api/users/{user} */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            $user->load(['role', 'zone', 'church'])
        );
    }

    /** PUT /api/users/{user} */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // ministry_id is immutable — strip it even if someone sneaks it in
        unset($data['ministry_id']);

        $user->update($data);
        return $this->successResponse($user->fresh()->load('role'), 'User updated successfully.');
    }

    /** DELETE /api/users/{user} */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->errorResponse('You cannot delete your own account.', 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->noContentResponse('User deleted successfully.');
    }

    /** POST /api/users/{user}/toggle-status */
    public function toggleStatus(User $user): JsonResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $status = $user->is_active ? 'activated' : 'deactivated';
        return $this->successResponse(['is_active' => $user->is_active], "User {$status} successfully.");
    }
}
