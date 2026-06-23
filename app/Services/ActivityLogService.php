<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ActivityLogService
{
    // ---------------------------------------------------------------
    // Paginated log listing
    // ---------------------------------------------------------------

    /**
     * Return a paginated, role-scoped list of activity logs.
     *
     * Accepted filters: module, action, user_id, from, to,
     *                   status_code, method, record_type, search
     */
    public function paginate(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,name,email')
            ->when($filters['module']      ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($filters['action']      ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['status_code'] ?? null, fn ($q, $v) => $q->where('status_code', $v))
            ->when($filters['method']      ?? null, fn ($q, $v) => $q->where('method', strtoupper($v)))
            ->when($filters['record_type'] ?? null, fn ($q, $v) => $q->where('record_type', $v))
            ->when(
                ($filters['user_id'] ?? null) && $actor->isAtLeast('zone_admin'),
                fn ($q) => $q->where('user_id', $filters['user_id'])
            )
            ->when(
                ($filters['from'] ?? null) && ($filters['to'] ?? null),
                fn ($q) => $q->whereBetween(
                    'performed_at',
                    [$filters['from'] . ' 00:00:00', $filters['to'] . ' 23:59:59']
                )
            )
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where(fn ($s) =>
                    $s->where('description', 'like', "%{$v}%")
                      ->orWhere('url', 'like', "%{$v}%")
                      ->orWhere('ip_address', 'like', "%{$v}%")
                )
            );

        $this->applyScope($query, $actor);

        return $query->orderByDesc('performed_at')->paginate($perPage);
    }

    // ---------------------------------------------------------------
    // Analytics: activity breakdown
    // ---------------------------------------------------------------

    /**
     * Return a breakdown of activity logs by module, action, user, and time trend.
     */
    public function stats(User $actor, array $params = []): array
    {
        $period  = $this->resolvePeriod($params);
        $groupBy = $params['group_by'] ?? 'day';

        $base = ActivityLog::whereBetween(
            'performed_at',
            [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']
        );
        $this->applyScope($base, $actor);

        $byModule = (clone $base)
            ->select('module', DB::raw('COUNT(*) as count'))
            ->groupBy('module')
            ->orderByDesc('count')
            ->get();

        $byAction = (clone $base)
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $byUser = (clone $base)
            ->join('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(*) as count'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $trend = (clone $base)
            ->select(
                DB::raw($this->dateTrunc($groupBy) . ' as period_label'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        return [
            'period'    => $period,
            'group_by'  => $groupBy,
            'total'     => (clone $base)->count(),
            'by_module' => $byModule,
            'by_action' => $byAction,
            'top_users' => $byUser,
            'trend'     => $trend,
        ];
    }

    // ---------------------------------------------------------------
    // Analytics: API usage
    // ---------------------------------------------------------------

    /**
     * Return API-usage metrics: hourly distribution, status codes, error rate, active users.
     */
    public function usage(User $actor, array $params = []): array
    {
        $period = $this->resolvePeriod($params);

        $base = ActivityLog::whereBetween(
            'performed_at',
            [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']
        );
        $this->applyScope($base, $actor);

        $total          = (clone $base)->count();
        $failed         = (clone $base)->where('status_code', '>=', 400)->count();
        $activeUsers    = (clone $base)->distinct('user_id')->count('user_id');

        $hourly = (clone $base)
            ->select(DB::raw('HOUR(performed_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $statusCodes = (clone $base)
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get();

        $topEndpoints = (clone $base)
            ->select('url', 'method', DB::raw('COUNT(*) as count'))
            ->groupBy('url', 'method')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'period'          => $period,
            'total_requests'  => $total,
            'failed_requests' => $failed,
            'error_rate'      => $total > 0 ? round($failed / $total * 100, 2) : 0,
            'active_users'    => $activeUsers,
            'hourly'          => $hourly,
            'status_codes'    => $statusCodes,
            'top_endpoints'   => $topEndpoints,
        ];
    }

    // ---------------------------------------------------------------
    // Analytics: login activity
    // ---------------------------------------------------------------

    /**
     * Login and logout event breakdown — timeline, unique users, peak hours.
     */
    public function loginActivity(User $actor, array $params = []): array
    {
        $period  = $this->resolvePeriod($params);
        $groupBy = $params['group_by'] ?? 'day';

        $base = ActivityLog::whereBetween(
            'performed_at',
            [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']
        )->where('module', 'auth');
        $this->applyScope($base, $actor);

        $byAction = (clone $base)
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $trend = (clone $base)
            ->where('action', 'login')
            ->select(
                DB::raw($this->dateTrunc($groupBy) . ' as period_label'),
                DB::raw('COUNT(*) as logins'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        $peakHours = (clone $base)
            ->where('action', 'login')
            ->select(DB::raw('HOUR(performed_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $recentLogins = (clone $base)
            ->where('action', 'login')
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'ip_address', 'user_agent', 'performed_at')
            ->orderByDesc('performed_at')
            ->limit(20)
            ->get();

        return [
            'period'         => $period,
            'group_by'       => $groupBy,
            'total_logins'   => (clone $base)->where('action', 'login')->count(),
            'total_logouts'  => (clone $base)->where('action', 'logout')->count(),
            'unique_users'   => (clone $base)->where('action', 'login')->distinct('user_id')->count('user_id'),
            'by_action'      => $byAction,
            'trend'          => $trend,
            'peak_hours'     => $peakHours,
            'recent_logins'  => $recentLogins,
        ];
    }

    // ---------------------------------------------------------------
    // Analytics: error logs
    // ---------------------------------------------------------------

    /**
     * Breakdown of failed (4xx / 5xx) requests.
     * ZoneAdmin and above only — useful for support and debugging.
     */
    public function errorLogs(User $actor, array $params = []): array
    {
        $period = $this->resolvePeriod($params);

        $base = ActivityLog::whereBetween(
            'performed_at',
            [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']
        )->where('status_code', '>=', 400);
        $this->applyScope($base, $actor);

        $byCode = (clone $base)
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get();

        $byEndpoint = (clone $base)
            ->select('url', 'method', 'status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('url', 'method', 'status_code')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        $byUser = (clone $base)
            ->join('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(*) as count'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recent = (clone $base)
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'status_code', 'method', 'url', 'ip_address', 'performed_at')
            ->orderByDesc('performed_at')
            ->limit(25)
            ->get();

        return [
            'period'      => $period,
            'total'       => (clone $base)->count(),
            'client_errors' => (clone $base)->whereBetween('status_code', [400, 499])->count(),
            'server_errors' => (clone $base)->where('status_code', '>=', 500)->count(),
            'by_code'     => $byCode,
            'by_endpoint' => $byEndpoint,
            'by_user'     => $byUser,
            'recent'      => $recent,
        ];
    }

    // ---------------------------------------------------------------
    // Analytics: audit trail for a record or user
    // ---------------------------------------------------------------

    /**
     * Return the full audit trail for a specific record (record_type + record_id)
     * or all actions performed by a specific user.
     *
     * At least one of record_type+record_id or user_id must be supplied.
     * MinistryAdmin may query by user_id; ZoneAdmin may query records in their scope.
     */
    public function auditTrail(User $actor, array $params, int $perPage): LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,name,email')
            ->when(
                ($params['record_type'] ?? null) && ($params['record_id'] ?? null),
                fn ($q) => $q->where('record_type', $params['record_type'])
                             ->where('record_id', $params['record_id'])
            )
            ->when(
                ($params['user_id'] ?? null) && $actor->isMinistryAdmin(),
                fn ($q) => $q->where('user_id', $params['user_id'])
            )
            ->when($params['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($params['module'] ?? null, fn ($q, $v) => $q->where('module', $v));

        $this->applyScope($query, $actor);

        return $query->orderByDesc('performed_at')->paginate($perPage);
    }

    // ---------------------------------------------------------------
    // Analytics: top actors
    // ---------------------------------------------------------------

    /**
     * Return the most active users ranked by action count within the period.
     * Optionally filtered by module or action.
     */
    public function topActors(User $actor, array $params = []): array
    {
        $period = $this->resolvePeriod($params);
        $limit  = min((int) ($params['limit'] ?? 10), 50);

        $base = ActivityLog::whereBetween(
            'performed_at',
            [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']
        )
            ->when($params['module'] ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($params['action'] ?? null, fn ($q, $v) => $q->where('action', $v));
        $this->applyScope($base, $actor);

        $actors = (clone $base)
            ->join('users', 'activity_logs.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('COUNT(DISTINCT activity_logs.module) as modules_used'),
                DB::raw('MAX(activity_logs.performed_at) as last_action_at')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_actions')
            ->limit($limit)
            ->get();

        return [
            'period'  => $period,
            'total'   => (clone $base)->count(),
            'actors'  => $actors,
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Apply role-based scope to any ActivityLog query builder.
     */
    private function applyScope($query, User $actor): void
    {
        $scope = $actor->accessScope();

        match ($scope['level']) {
            'zone'   => $query->where('zone_id', $scope['zone_id']),
            'church' => $query->where('church_id', $scope['church_id']),
            default  => null, // ministry → unrestricted
        };
    }

    /**
     * Resolve the date period from params, defaulting to the last 30 days.
     */
    private function resolvePeriod(array $params): array
    {
        if (($params['from'] ?? null) && ($params['to'] ?? null)) {
            return ['from' => $params['from'], 'to' => $params['to']];
        }

        $days = max(1, min((int) ($params['period'] ?? 30), 365));

        return [
            'from' => now()->subDays($days)->toDateString(),
            'to'   => now()->toDateString(),
        ];
    }

    /**
     * Return the MySQL DATE_FORMAT expression for the requested grouping.
     */
    private function dateTrunc(string $groupBy): string
    {
        return match ($groupBy) {
            'week'  => "DATE_FORMAT(performed_at, '%Y-%u')",
            'month' => "DATE_FORMAT(performed_at, '%Y-%m')",
            default => "DATE(performed_at)",
        };
    }
}
