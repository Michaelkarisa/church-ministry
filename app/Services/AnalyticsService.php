<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ---------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------

    private function periodClause(array $params): array
    {
        $from = $params['from'] ?? null;
        $to   = $params['to']   ?? null;
        $days = (int) ($params['period'] ?? 30);

        if ($from && $to) {
            return ['from' => $from, 'to' => $to];
        }

        return [
            'from' => now()->subDays($days)->toDateString(),
            'to'   => now()->toDateString(),
        ];
    }

    private function groupByFormat(string $groupBy): string
    {
        return match ($groupBy) {
            'week'  => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    private function baseTransactionQuery(array $filters)
    {
        $period  = $this->periodClause($filters);
        $groupBy = $filters['group_by'] ?? 'day';

        return Transaction::query()
            ->whereBetween('transaction_date', [$period['from'], $period['to']]);
    }

    // ---------------------------------------------------------------
    // Church-level analytics
    // ---------------------------------------------------------------

    public function churchSummary(string $churchId, array $params = []): array
    {
        $period = $this->periodClause($params);

        $base = Transaction::where('church_id', $churchId)
            ->whereBetween('transaction_date', [$period['from'], $period['to']]);

        $total     = (clone $base)->sum('amount');
        $count     = (clone $base)->count();
        $verified  = (clone $base)->where('is_verified', true)->sum('amount');
        $pending   = (clone $base)->where('is_verified', false)->sum('amount');

        // By category
        $byCategory = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select('transaction_types.category', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('transaction_types.category')
            ->get();

        // By type
        $byType = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select(
                'transaction_types.id',
                'transaction_types.name',
                'transaction_types.category',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('transaction_types.id', 'transaction_types.name', 'transaction_types.category')
            ->orderByDesc('total')
            ->get();

        // By service type
        $byService = (clone $base)
            ->select('service_type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('service_type')
            ->get();

        return [
            'period'       => $period,
            'church_id'    => $churchId,
            'total_amount' => round((float) $total, 2),
            'total_count'  => $count,
            'verified'     => round((float) $verified, 2),
            'pending'      => round((float) $pending, 2),
            'by_category'  => $byCategory,
            'by_type'      => $byType,
            'by_service'   => $byService,
            'currency'     => config('church.default_currency'),
        ];
    }

    public function churchTrend(string $churchId, array $params = []): array
    {
        $period  = $this->periodClause($params);
        $groupBy = $params['group_by'] ?? 'day';
        $fmt     = $this->groupByFormat($groupBy);

        $trend = Transaction::where('church_id', $churchId)
            ->whereBetween('transaction_date', [$period['from'], $period['to']])
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '{$fmt}') as period_label"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        return ['period' => $period, 'group_by' => $groupBy, 'trend' => $trend];
    }

       public function churchTopMembers(string $churchId, array $params = []): array
    {
        $period = $this->periodClause($params);
        $limit  = min((int) ($params['limit'] ?? 10), 50);

        $members = Transaction::where('transactions.church_id', $churchId) // <-- Prefixed
            ->whereBetween('transactions.transaction_date', [$period['from'], $period['to']]) // <-- Prefixed
            ->whereNotNull('transactions.member_id') // <-- Prefixed
            ->join('members', 'transactions.member_id', '=', 'members.id')
            ->select(
                'members.id',
                DB::raw("CONCAT(members.first_name, ' ', members.last_name) as full_name"),
                'members.member_number',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(transactions.id) as transaction_count')
            )
            ->groupBy('members.id', 'members.first_name', 'members.last_name', 'members.member_number')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return ['period' => $period, 'top_members' => $members];
    }

    // ---------------------------------------------------------------
    // Zone-level analytics
    // ---------------------------------------------------------------

    public function zoneSummary(string $zoneId, array $params = []): array
    {
        $period = $this->periodClause($params);

        $churchIds = Church::where('zone_id', $zoneId)->pluck('id');

        $base = Transaction::whereIn('church_id', $churchIds)
            ->whereBetween('transaction_date', [$period['from'], $period['to']]);

        $total    = (clone $base)->sum('amount');
        $count    = (clone $base)->count();
        $verified = (clone $base)->where('is_verified', true)->sum('amount');

        $byCategory = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select('transaction_types.category', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('transaction_types.category')
            ->get();

        $byType = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select(
                'transaction_types.id',
                'transaction_types.name',
                'transaction_types.category',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('transaction_types.id', 'transaction_types.name', 'transaction_types.category')
            ->orderByDesc('total')
            ->get();

        return [
            'period'       => $period,
            'zone_id'      => $zoneId,
            'total_amount' => round((float) $total, 2),
            'total_count'  => $count,
            'verified'     => round((float) $verified, 2),
            'churches_count' => $churchIds->count(),
            'by_category'  => $byCategory,
            'by_type'      => $byType,
            'currency'     => config('church.default_currency'),
        ];
    }

    public function zoneChurchesComparison(string $zoneId, array $params = []): array
    {
        $period = $this->periodClause($params);

        $churches = Church::where('zone_id', $zoneId)
            ->with('transactions' , fn ($q) =>
                $q->whereBetween('transaction_date', [$period['from'], $period['to']])
            )
            ->get()
            ->map(function ($church) use ($period) {
                $txns  = $church->transactions;
                return [
                    'church_id'    => $church->id,
                    'church_name'  => $church->name,
                    'church_code'  => $church->code,
                    'total_amount' => round((float) $txns->sum('amount'), 2),
                    'count'        => $txns->count(),
                    'members'      => $church->members()->where('is_active', true)->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return ['period' => $period, 'zone_id' => $zoneId, 'churches' => $churches];
    }

    public function zoneTrend(string $zoneId, array $params = []): array
    {
        $period    = $this->periodClause($params);
        $groupBy   = $params['group_by'] ?? 'day';
        $fmt       = $this->groupByFormat($groupBy);
        $churchIds = Church::where('zone_id', $zoneId)->pluck('id');

        $trend = Transaction::whereIn('church_id', $churchIds)
            ->whereBetween('transaction_date', [$period['from'], $period['to']])
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '{$fmt}') as period_label"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        return ['period' => $period, 'group_by' => $groupBy, 'trend' => $trend];
    }

    // ---------------------------------------------------------------
    // Ministry-level analytics
    // ---------------------------------------------------------------

    public function ministrySummary(array $params = []): array
    {
        $period = $this->periodClause($params);

        $base = Transaction::whereBetween('transaction_date', [$period['from'], $period['to']]);

        $total    = (clone $base)->sum('amount');
        $count    = (clone $base)->count();
        $verified = (clone $base)->where('is_verified', true)->sum('amount');
        $pending  = (clone $base)->where('is_verified', false)->sum('amount');

        $byCategory = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select('transaction_types.category', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('transaction_types.category')
            ->orderByDesc('total')
            ->get();

        $byType = (clone $base)
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select(
                'transaction_types.id',
                'transaction_types.name',
                'transaction_types.category',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('transaction_types.id', 'transaction_types.name', 'transaction_types.category')
            ->orderByDesc('total')
            ->get();

        return [
            'period'       => $period,
            'total_amount' => round((float) $total, 2),
            'total_count'  => $count,
            'verified'     => round((float) $verified, 2),
            'pending'      => round((float) $pending, 2),
            'by_category'  => $byCategory,
            'by_type'      => $byType,
            'currency'     => config('church.default_currency'),
        ];
    }

    public function ministryZonesComparison(array $params = []): array
    {
        $period = $this->periodClause($params);

        $zones = \App\Models\Zone::with(['churches'])->get()
            ->map(function ($zone) use ($period) {
                $churchIds = $zone->churches->pluck('id');
                $txns = Transaction::whereIn('church_id', $churchIds)
                    ->whereBetween('transaction_date', [$period['from'], $period['to']]);

                return [
                    'zone_id'      => $zone->id,
                    'zone_name'    => $zone->name,
                    'zone_code'    => $zone->code,
                    'churches'     => $zone->churches->count(),
                    'total_amount' => round((float) (clone $txns)->sum('amount'), 2),
                    'count'        => (clone $txns)->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return ['period' => $period, 'zones' => $zones];
    }

    public function ministryTopChurches(array $params = []): array
    {
        $period = $this->periodClause($params);
        $limit  = min((int) ($params['limit'] ?? 10), 50);

        $churches = Transaction::whereBetween('transaction_date', [$period['from'], $period['to']])
            ->join('churches', 'transactions.church_id', '=', 'churches.id')
            ->join('zones', 'churches.zone_id', '=', 'zones.id')
            ->select(
                'churches.id',
                'churches.name as church_name',
                'churches.code as church_code',
                'zones.name as zone_name',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(transactions.id) as count')
            )
            ->groupBy('churches.id', 'churches.name', 'churches.code', 'zones.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return ['period' => $period, 'top_churches' => $churches];
    }

    public function ministryTrend(array $params = []): array
    {
        $period  = $this->periodClause($params);
        $groupBy = $params['group_by'] ?? 'month';
        $fmt     = $this->groupByFormat($groupBy);

        $trend = Transaction::whereBetween('transaction_date', [$period['from'], $period['to']])
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '{$fmt}') as period_label"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        return ['period' => $period, 'group_by' => $groupBy, 'trend' => $trend];
    }

    public function ministryOverview(): array
    {
        // Because there is exactly one ministry, platform totals == ministry totals.
        return [
            'ministry'   => \App\Models\Ministry::current()->only([
                'id', 'name', 'code', 'currency_code',
            ]),
            'totals' => [
                'zones'    => \App\Models\Zone::count(),
                'churches' => Church::count(),
                'members'  => \App\Models\Member::where('is_active', true)->count(),
                'users'    => User::where('is_active', true)->count(),
            ],
            'this_month' => $this->ministrySummary(['period' => 30]),
            'last_month' => $this->ministrySummary([
                'from' => now()->subMonths(2)->startOfMonth()->toDateString(),
                'to'   => now()->subMonth()->endOfMonth()->toDateString(),
            ]),
            'this_year'  => $this->ministrySummary([
                'from' => now()->startOfYear()->toDateString(),
                'to'   => now()->toDateString(),
            ]),
        ];
    }

    // ---------------------------------------------------------------
    // Activity log analytics
    // ---------------------------------------------------------------

    public function activityStats(User $user, array $params = []): array
    {
        $period = $this->periodClause($params);
        $scope  = $user->accessScope();

        $base = ActivityLog::whereBetween('performed_at', [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']);

        if ($scope['level'] === 'church') {
            $base->where('church_id', $scope['church_id']);
        } elseif ($scope['level'] === 'zone') {
            $base->where('zone_id', $scope['zone_id']);
        }

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
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $trend = (clone $base)
            ->select(
                DB::raw("DATE(performed_at) as day"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'period'    => $period,
            'total'     => (clone $base)->count(),
            'by_module' => $byModule,
            'by_action' => $byAction,
            'by_user'   => $byUser,
            'trend'     => $trend,
        ];
    }

    // ---------------------------------------------------------------
    // Usage analytics (API + user activity)
    // ---------------------------------------------------------------

    public function usageStats(User $user, array $params = []): array
    {
        $period = $this->periodClause($params);
        $scope  = $user->accessScope();

        $base = ActivityLog::whereBetween('performed_at', [$period['from'] . ' 00:00:00', $period['to'] . ' 23:59:59']);

        if ($scope['level'] === 'church') {
            $base->where('church_id', $scope['church_id']);
        } elseif ($scope['level'] === 'zone') {
            $base->where('zone_id', $scope['zone_id']);
        }

        $hourlyDistribution = (clone $base)
            ->select(DB::raw('HOUR(performed_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $statusCodes = (clone $base)
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get();

        // Removed the pasted SQL error message from here
        $failedRequests = (clone $base)
            ->where('status_code', '>=', 400)
            ->count();

        return [
            'period'               => $period,
            'total_requests'       => (clone $base)->count(),
            'failed_requests'      => $failedRequests,
            'hourly_distribution'  => $hourlyDistribution,
            'status_codes'         => $statusCodes,
            'active_users'         => (clone $base)->distinct('user_id')->count('user_id'),
        ];
    }
}
