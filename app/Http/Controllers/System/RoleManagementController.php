<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System route for role management.
 *
 * All routes in this controller are protected by the `zone.admin` middleware,
 * which permits MinistryAdmin (level 1) and ZoneAdmin (level 2).
 * Fine-grained scope enforcement is delegated to RoleService.
 *
 * Route prefix: /api/system/roles
 */
class RoleManagementController extends Controller
{
    use ApiResponse;

    public function __construct(private RoleService $roleService) {}

    // ---------------------------------------------------------------
    // Catalogue
    // ---------------------------------------------------------------

    /**
     * GET /api/system/roles
     *
     * Returns the subset of roles that the authenticated actor is
     * permitted to assign. MinistryAdmin sees zone_admin + church_admin;
     * ZoneAdmin sees only church_admin.
     */
    public function listAssignableRoles(Request $request): JsonResponse
    {
        $roles = $this->roleService->assignableRoles($request->user());

        return $this->successResponse($roles, 'Assignable roles retrieved.');
    }

    // ---------------------------------------------------------------
    // Managed-user listing
    // ---------------------------------------------------------------

    /**
     * GET /api/system/roles/users
     *
     * Paginated list of users whose roles the actor may manage.
     *
     * Query params: search, role_id, is_active, per_page
     */
    public function listManagedUsers(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->roleService->listManagedUsers(
            $request->user(),
            [
                'search'    => $request->search,
                'role_id'   => $request->role_id,
                'is_active' => $request->filled('is_active')
                    ? $request->boolean('is_active')
                    : null,
            ],
            $perPage,
        );

        return $this->successResponse($paginator, 'Managed users retrieved.');
    }

    /**
     * GET /api/system/roles/users/{user}
     *
     * Show a single user the actor is permitted to manage.
     */
    public function showManagedUser(Request $request, User $user): JsonResponse
    {
        if (! $this->roleService->canManageUser($request->user(), $user)) {
            return $this->forbiddenResponse('You do not have permission to view this user\'s role details.');
        }

        $user->load(['role', 'zone', 'church']);

        return $this->successResponse($user, 'User retrieved.');
    }

    // ---------------------------------------------------------------
    // Role assignment
    // ---------------------------------------------------------------

    /**
     * POST /api/system/roles/users/{user}/assign
     *
     * Assign a new role to the target user.
     *
     * Body: { "role_id": "<uuid>" }
     *
     * MinistryAdmin may assign: zone_admin, church_admin
     * ZoneAdmin     may assign: church_admin (within their zone only)
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ]);

        try {
            $updated = $this->roleService->assignRole(
                $request->user(),
                $user,
                $request->role_id,
            );

            return $this->successResponse(
                $updated,
                "Role '{$updated->role->display_name}' assigned to {$updated->name}."
            );
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    // ---------------------------------------------------------------
    // Role revocation
    // ---------------------------------------------------------------

    /**
     * DELETE /api/system/roles/users/{user}/revoke
     *
     * Revoke the target user's role entirely (sets role_id → null).
     * Immediately invalidates all of the user's active tokens.
     *
     * MinistryAdmin may revoke: zone_admin, church_admin
     * ZoneAdmin     may revoke: church_admin (within their zone only)
     */
    public function revokeRole(Request $request, User $user): JsonResponse
    {
        try {
            $updated = $this->roleService->revokeRole($request->user(), $user);

            return $this->successResponse(
                $updated,
                "Role revoked from {$updated->name}. Their active sessions have been terminated."
            );
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
