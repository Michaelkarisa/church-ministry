<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(private ActivityLogService $logService) {}

    // ---------------------------------------------------------------
    // Log listing
    // ---------------------------------------------------------------

    /**
     * GET /api/analytics/logs
     *
     * Paginated activity log listing, role-scoped.
     * Query params: module, action, user_id, from, to,
     *               status_code, method, record_type, search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        $paginator = $this->logService->paginate(
            $request->user(),
            [
                'module'      => $request->module,
                'action'      => $request->action,
                'user_id'     => $request->user_id,
                'from'        => $request->from,
                'to'          => $request->to,
                'status_code' => $request->status_code,
                'method'      => $request->method_filter, // 'method' is reserved on Request
                'record_type' => $request->record_type,
                'search'      => $request->search,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    // ---------------------------------------------------------------
    // Analytics endpoints
    // ---------------------------------------------------------------

    /**
     * GET /api/analytics/logs/stats
     *
     * Activity breakdown: by module, by action, top users, trend.
     * Query params: from, to, period (days), group_by (day|week|month)
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->logService->stats($request->user(), $request->query())
        );
    }

    /**
     * GET /api/analytics/logs/usage
     *
     * API usage metrics: error rate, status codes, hourly distribution,
     * top endpoints, active unique users.
     * Query params: from, to, period (days)
     */
    public function usage(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->logService->usage($request->user(), $request->query())
        );
    }

    /**
     * GET /api/analytics/logs/login-activity
     *
     * Login/logout timeline, peak hours, unique sessions.
     * Query params: from, to, period (days), group_by (day|week|month)
     */
    public function loginActivity(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->logService->loginActivity($request->user(), $request->query())
        );
    }

    /**
     * GET /api/analytics/logs/errors
     *
     * 4xx / 5xx error breakdown by code, endpoint, and user.
     * Restricted to ZoneAdmin and above.
     * Query params: from, to, period (days)
     */
    public function errorLogs(Request $request): JsonResponse
    {
        if (! $request->user()->isAtLeast('zone_admin')) {
            return $this->forbiddenResponse('Error log analytics require Zone Administrator access.');
        }

        return $this->successResponse(
            $this->logService->errorLogs($request->user(), $request->query())
        );
    }

    /**
     * GET /api/analytics/logs/audit-trail
     *
     * Full ordered change history for a specific record or user.
     * Params: record_type + record_id (any role), user_id (MinistryAdmin only),
     *         module, action, per_page
     */
    public function auditTrail(Request $request): JsonResponse
    {
        $request->validate([
            'record_type' => ['nullable', 'string'],
            'record_id'   => ['nullable', 'uuid'],
            'user_id'     => ['nullable', 'uuid'],
        ]);

        $hasRecord = $request->filled('record_type') && $request->filled('record_id');
        $hasUser   = $request->filled('user_id');

        if (! $hasRecord && ! $hasUser) {
            return $this->errorResponse(
                'Provide either record_type + record_id, or user_id to fetch an audit trail.',
                422
            );
        }

        if ($hasUser && ! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Filtering audit trails by user requires Ministry Administrator access.');
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return $this->successResponse(
            $this->logService->auditTrail($request->user(), $request->query(), $perPage)
        );
    }

    /**
     * GET /api/analytics/logs/top-actors
     *
     * Most active users ranked by action count for the period.
     * Query params: from, to, period (days), module, action, limit (max 50)
     */
    public function topActors(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->logService->topActors($request->user(), $request->query())
        );
    }
}
