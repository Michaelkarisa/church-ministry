<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private UserService $userService) {}

    /** GET /api/users */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->userService->index(
            [
                'search'    => $request->search,
                'role_id'   => $request->role_id,
                'zone_id'   => $request->zone_id,
                'church_id' => $request->church_id,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->store($request->validated());

        return $this->createdResponse($user->load('role'), 'User created successfully.');
    }

    /** GET /api/users/{user} */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse($this->userService->show($user));
    }

    /** PUT /api/users/{user} */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->successResponse($user, 'User updated successfully.');
    }

    /** DELETE /api/users/{user} */
    public function destroy(Request $request, User $user): JsonResponse
    {
        try {
            $this->userService->destroy($user, $request->user());
            return $this->noContentResponse('User deleted successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /** POST /api/users/{user}/toggle-status */
    public function toggleStatus(User $user): JsonResponse
    {
        $user   = $this->userService->toggleStatus($user);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(['is_active' => $user->is_active], "User {$status} successfully.");
    }
}
