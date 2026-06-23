<?php

use App\Http\Controllers\Analytics\ActivityLogController;
use App\Http\Controllers\Analytics\ChurchAnalyticsController;
use App\Http\Controllers\Analytics\MinistryAnalyticsController;
use App\Http\Controllers\Analytics\ZoneAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics Routes  —  v1
|--------------------------------------------------------------------------
|
| All URLs live under:  /api/v1/analytics/...
|
| Versioning middleware stamps every response with:
|   X-API-Version         : v1
|   X-API-Current-Version : v1
|
| Base middleware: api.version:v1 + auth:sanctum + throttle:api
|
| Additional middleware per group:
|   /zone      → zone.admin     (ZoneAdmin or MinistryAdmin)
|   /ministry  → ministry.admin (MinistryAdmin only)
|
| Role-based data scope is applied inside each service automatically:
|   MinistryAdmin → unrestricted across all zones and churches
|   ZoneAdmin     → restricted to their zone and its churches
|   ChurchAdmin   → restricted to their own church
|
*/

Route::prefix('v1')
    ->middleware('api.version:v1')
    ->name('v1.')
    ->group(function () {

        Route::prefix('analytics')
            ->middleware(['auth:sanctum', 'rate.limit:api'])
            ->name('analytics.')
            ->group(function () {

                /*
                |--------------------------------------------------------------
                | Activity Logs  —  /api/v1/analytics/logs
                |--------------------------------------------------------------
                | All roles may call these endpoints.
                | The service automatically narrows results to the caller's scope.
                */
                Route::prefix('logs')->name('logs.')->group(function () {

                    /*
                     * GET /api/v1/analytics/logs
                     * Query: module, action, user_id, from, to, status_code,
                     *        method_filter, record_type, search, per_page
                     *
                     * Paginated, role-scoped activity log listing.
                     */
                    Route::get('/', [ActivityLogController::class, 'index'])
                        ->name('index');

                    /*
                     * GET /api/v1/analytics/logs/stats
                     * Query: from, to, period, group_by (day|week|month)
                     *
                     * Breakdown by module, action, top users, and trend.
                     */
                    Route::get('/stats', [ActivityLogController::class, 'stats'])
                        ->name('stats');

                    /*
                     * GET /api/v1/analytics/logs/usage
                     * Query: from, to, period
                     *
                     * API usage metrics: error rate, status codes, hourly
                     * distribution, top endpoints, active unique users.
                     */
                    Route::get('/usage', [ActivityLogController::class, 'usage'])
                        ->name('usage');

                    /*
                     * GET /api/v1/analytics/logs/login-activity
                     * Query: from, to, period, group_by (day|week|month)
                     *
                     * Login/logout timeline, peak hours, unique sessions.
                     */
                    Route::get('/login-activity', [ActivityLogController::class, 'loginActivity'])
                        ->name('login-activity');

                    /*
                     * GET /api/v1/analytics/logs/errors
                     * Query: from, to, period
                     *
                     * 4xx / 5xx breakdown by code, endpoint, and user.
                     * Restricted to ZoneAdmin and above.
                     */
                    Route::get('/errors', [ActivityLogController::class, 'errorLogs'])
                        ->name('errors');

                    /*
                     * GET /api/v1/analytics/logs/audit-trail
                     * Query: record_type + record_id  OR  user_id (MinistryAdmin),
                     *        module, action, per_page
                     *
                     * Full ordered change history for a record or user.
                     */
                    Route::get('/audit-trail', [ActivityLogController::class, 'auditTrail'])
                        ->name('audit-trail');

                    /*
                     * GET /api/v1/analytics/logs/top-actors
                     * Query: from, to, period, module, action, limit
                     *
                     * Most active users ranked by action count for the period.
                     */
                    Route::get('/top-actors', [ActivityLogController::class, 'topActors'])
                        ->name('top-actors');

                });

                /*
                |--------------------------------------------------------------
                | Church Analytics  —  /api/v1/analytics/church
                |--------------------------------------------------------------
                | ChurchAdmin  → always their own church.
                | ZoneAdmin    → supply ?church_id= (within their zone).
                | MinistryAdmin → any church via ?church_id=.
                */
                Route::prefix('church')->name('church.')->group(function () {

                    /*
                     * GET /api/v1/analytics/church/summary
                     * Query: church_id (zone/ministry only), from, to, period
                     *
                     * Transaction totals, verification split, by-category and
                     * by-type breakdown for the resolved church.
                     */
                    Route::get('/summary', [ChurchAnalyticsController::class, 'summary'])
                        ->name('summary');

                    /*
                     * GET /api/v1/analytics/church/trend
                     * Query: church_id (zone/ministry only), from, to, period, group_by
                     *
                     * Daily/weekly/monthly transaction volume trend.
                     */
                    Route::get('/trend', [ChurchAnalyticsController::class, 'trend'])
                        ->name('trend');

                    /*
                     * GET /api/v1/analytics/church/top-members
                     * Query: church_id (zone/ministry only), from, to, period, limit
                     *
                     * Top contributing members ranked by total transaction amount.
                     */
                    Route::get('/top-members', [ChurchAnalyticsController::class, 'topMembers'])
                        ->name('top-members');

                });

                /*
                |--------------------------------------------------------------
                | Zone Analytics  —  /api/v1/analytics/zone
                |--------------------------------------------------------------
                | ZoneAdmin     → always their own zone.
                | MinistryAdmin → supply ?zone_id=.
                */
                Route::prefix('zone')
                    ->middleware('zone.admin')
                    ->name('zone.')
                    ->group(function () {

                        /*
                         * GET /api/v1/analytics/zone/summary
                         * Query: zone_id (ministry only), from, to, period
                         *
                         * Zone-wide transaction totals and breakdown.
                         */
                        Route::get('/summary', [ZoneAnalyticsController::class, 'summary'])
                            ->name('summary');

                        /*
                         * GET /api/v1/analytics/zone/churches-comparison
                         * Query: zone_id (ministry only), from, to, period
                         *
                         * Side-by-side church comparison within the zone.
                         */
                        Route::get('/churches-comparison', [ZoneAnalyticsController::class, 'churchesComparison'])
                            ->name('churches-comparison');

                        /*
                         * GET /api/v1/analytics/zone/trend
                         * Query: zone_id (ministry only), from, to, period, group_by
                         *
                         * Aggregated transaction trend for the entire zone.
                         */
                        Route::get('/trend', [ZoneAnalyticsController::class, 'trend'])
                            ->name('trend');

                    });

                /*
                |--------------------------------------------------------------
                | Ministry Analytics  —  /api/v1/analytics/ministry
                |--------------------------------------------------------------
                | Restricted to MinistryAdmin only.
                */
                Route::prefix('ministry')
                    ->middleware('ministry.admin')
                    ->name('ministry.')
                    ->group(function () {

                        /*
                         * GET /api/v1/analytics/ministry/overview
                         *
                         * Dashboard totals (zones, churches, members, users)
                         * + this-month, last-month, and YTD summaries.
                         */
                        Route::get('/overview', [MinistryAnalyticsController::class, 'overview'])
                            ->name('overview');

                        /*
                         * GET /api/v1/analytics/ministry/summary
                         * Query: from, to, period
                         *
                         * Full transaction breakdown across all zones.
                         */
                        Route::get('/summary', [MinistryAnalyticsController::class, 'summary'])
                            ->name('summary');

                        /*
                         * GET /api/v1/analytics/ministry/zones-comparison
                         * Query: from, to, period
                         *
                         * Side-by-side zone comparison: total amount, count, churches.
                         */
                        Route::get('/zones-comparison', [MinistryAnalyticsController::class, 'zonesComparison'])
                            ->name('zones-comparison');

                        /*
                         * GET /api/v1/analytics/ministry/top-churches
                         * Query: from, to, period, limit
                         *
                         * Top contributing churches ministry-wide by total amount.
                         */
                        Route::get('/top-churches', [MinistryAnalyticsController::class, 'topChurches'])
                            ->name('top-churches');

                        /*
                         * GET /api/v1/analytics/ministry/trend
                         * Query: from, to, period, group_by
                         *
                         * Ministry-wide transaction volume trend.
                         */
                        Route::get('/trend', [MinistryAnalyticsController::class, 'trend'])
                            ->name('trend');

                    });

            });

    });
