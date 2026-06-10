<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Scoped by role
class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    /** GET /api/activity-logs */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->get('per_page', 20), 100);
        $scope   = $user->accessScope();

        $query = ActivityLog::with('user:id,name,email')
            ->when($request->module, fn ($q) => $q->where('module', $request->module))
            ->when($request->action, fn ($q) => $q->where('action', $request->action))
            ->when($request->user_id && $user->isMinistryAdmin(), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->from && $request->to, fn ($q) =>
                $q->whereBetween('performed_at', [$request->from . ' 00:00:00', $request->to . ' 23:59:59'])
            );

        // Scope by role
        match ($scope['level']) {
            'ministry' => null, // sees all
            'zone'     => $query->where('zone_id', $scope['zone_id']),
            default    => $query->where('church_id', $scope['church_id']),
        };

        return $this->successResponse(
            $query->orderByDesc('performed_at')->paginate($perPage)
        );
    }

    /** GET /api/activity-logs/stats */
    public function stats(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->activityStats($request->user(), $request->query())
        );
    }

    /** GET /api/activity-logs/usage */
    public function usage(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->usageStats($request->user(), $request->query())
        );
    }
}
