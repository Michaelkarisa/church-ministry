<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Accessible by: MinistryAdmin only
class MinistryAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    /** GET /api/analytics/ministry/overview */
    public function overview(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->ministryOverview()
        );
    }

    /** GET /api/analytics/ministry/summary */
    public function summary(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->ministrySummary($request->query())
        );
    }

    /** GET /api/analytics/ministry/zones-comparison */
    public function zonesComparison(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->ministryZonesComparison($request->query())
        );
    }

    /** GET /api/analytics/ministry/top-churches */
    public function topChurches(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->ministryTopChurches($request->query())
        );
    }

    /** GET /api/analytics/ministry/trend */
    public function trend(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->ministryTrend($request->query())
        );
    }
}
