<?php

use App\Http\Controllers\System\RoleManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System Routes  —  v1
|--------------------------------------------------------------------------
|
| All URLs live under:  /api/v1/system/...
|
| Versioning middleware stamps every response with:
|   X-API-Version         : v1
|   X-API-Current-Version : v1
|
| All routes require:
|   api.version:v1   — version gate + response header stamping
|   auth:sanctum     — valid bearer token
|   zone.admin       — ZoneAdmin (level 2) or MinistryAdmin (level 1)
|
| Fine-grained scope is enforced inside RoleService:
|   MinistryAdmin (level 1) → manages zone_admins + church_admins ministry-wide
|   ZoneAdmin     (level 2) → manages church_admins within their zone only
|
*/

Route::prefix('v1')
    ->middleware('api.version:v1')
    ->name('v1.')
    ->group(function () {

        Route::prefix('system')
            ->middleware(['auth:sanctum', 'zone.admin', 'rate.limit:api'])
            ->name('system.')
            ->group(function () {

                /*
                |----------------------------------------------------------
                | Role Management  —  /api/v1/system/roles
                |----------------------------------------------------------
                */
                Route::prefix('roles')->name('roles.')->group(function () {

                    /*
                     * GET /api/v1/system/roles
                     *
                     * List roles the authenticated actor is permitted to assign.
                     *   MinistryAdmin → [zone_admin, church_admin]
                     *   ZoneAdmin     → [church_admin]
                     */
                    Route::get('/', [RoleManagementController::class, 'listAssignableRoles'])
                        ->name('index');

                    /*
                     * GET /api/v1/system/roles/users
                     * Query: search, role_id, is_active, per_page
                     *
                     * Paginated list of users whose roles the actor may manage.
                     */
                    Route::get('/users', [RoleManagementController::class, 'listManagedUsers'])
                        ->name('users.index');

                    /*
                     * GET /api/v1/system/roles/users/{user}
                     *
                     * Profile of a single user the actor manages.
                     */
                    Route::get('/users/{user}', [RoleManagementController::class, 'showManagedUser'])
                        ->name('users.show');

                    /*
                     * POST /api/v1/system/roles/users/{user}/assign
                     * Body: { "role_id": "<uuid>" }
                     *
                     * Assign a new role to the target user.
                     * Immediately revokes all existing tokens when the role changes.
                     *
                     * MinistryAdmin may assign: zone_admin, church_admin
                     * ZoneAdmin     may assign: church_admin (own zone only)
                     */
                    Route::post('/users/{user}/assign', [RoleManagementController::class, 'assignRole'])
                        ->name('users.assign');

                    /*
                     * DELETE /api/v1/system/roles/users/{user}/revoke
                     *
                     * Revoke the target user's role entirely (role_id → null).
                     * Immediately terminates all active sessions for that user.
                     *
                     * MinistryAdmin may revoke: zone_admin, church_admin
                     * ZoneAdmin     may revoke: church_admin (own zone only)
                     */
                    Route::delete('/users/{user}/revoke', [RoleManagementController::class, 'revokeRole'])
                        ->name('users.revoke');

                });

            });

    });
