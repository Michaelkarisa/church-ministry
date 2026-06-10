<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Accessible by: ZoneAdmin and MinistryAdmin
class ZoneAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    /** GET /api/analytics/zone/summary */
    public function summary(Request $request): JsonResponse
    {
        $zoneId = $this->resolveZoneId($request);
        if (! $zoneId) return $this->forbiddenResponse();

        return $this->successResponse(
            $this->analytics->zoneSummary($zoneId, $request->query())
        );
    }

    /** GET /api/analytics/zone/churches-comparison */
    public function churchesComparison(Request $request): JsonResponse
    {
        $zoneId = $this->resolveZoneId($request);
        if (! $zoneId) return $this->forbiddenResponse();

        return $this->successResponse(
            $this->analytics->zoneChurchesComparison($zoneId, $request->query())
        );
    }

    /** GET /api/analytics/zone/trend */
    public function trend(Request $request): JsonResponse
    {
        $zoneId = $this->resolveZoneId($request);
        if (! $zoneId) return $this->forbiddenResponse();

        return $this->successResponse(
            $this->analytics->zoneTrend($zoneId, $request->query())
        );
    }

    private function resolveZoneId(Request $request): ?string
    {
        $user = $request->user();

        if ($user->isZoneAdmin()) {
            return $user->zone_id;
        }

        // Ministry admin can specify zone
        return $request->zone_id ?: null;
    }
}
