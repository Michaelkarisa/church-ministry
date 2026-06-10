<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    /** GET /api/analytics/church/summary */
    public function summary(Request $request): JsonResponse
    {
        $churchId = $this->resolveChurchId($request);
        if (! $churchId) return $this->forbiddenResponse();

        $data = $this->analytics->churchSummary($churchId, $request->query());
        return $this->successResponse($data);
    }

    /** GET /api/analytics/church/trend */
    public function trend(Request $request): JsonResponse
    {
        $churchId = $this->resolveChurchId($request);
        if (! $churchId) return $this->forbiddenResponse();

        return $this->successResponse(
            $this->analytics->churchTrend($churchId, $request->query())
        );
    }

    /** GET /api/analytics/church/top-members */
    public function topMembers(Request $request): JsonResponse
    {
        $churchId = $this->resolveChurchId($request);
        if (! $churchId) return $this->forbiddenResponse();

        return $this->successResponse(
            $this->analytics->churchTopMembers($churchId, $request->query())
        );
    }

    private function resolveChurchId(Request $request): ?string
    {
        $user = $request->user();

        if ($user->isChurchAdmin()) {
            return $user->church_id;
        }

        // Zone/Ministry admins can query a specific church
        $churchId = $request->church_id;

        if ($user->isZoneAdmin()) {
            $church = \App\Models\Church::find($churchId);
            if (! $church || $church->zone_id !== $user->zone_id) return null;
            return $churchId;
        }

        // Ministry admin — any church
        return $churchId ?: null;
    }
}