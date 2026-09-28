<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The general/landing overview dashboard. Deliberately non-financial —
 * church counts per level, asset status, leadership and activity
 * indicators. Financial figures are a separate drill-down, queried
 * one church at a time via ChurchAnalyticsController / Event / Project.
 */
class StructureAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    /** GET /api/analytics/overview */
    public function overview(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->structureOverview($request->user())
        );
    }
}
