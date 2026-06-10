<?php

use App\Http\Controllers\Analytics\ActivityLogController;
use App\Http\Controllers\Analytics\ChurchAnalyticsController;
use App\Http\Controllers\Analytics\MinistryAnalyticsController;
use App\Http\Controllers\Analytics\ZoneAnalyticsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Church Ministry Platform API Routes
|--------------------------------------------------------------------------
|
| Hierarchy:    Ministry → Zone → Church → Member / Transaction
| Auth:         Laravel Sanctum (Bearer token)
| Versioning:   /api/v1/...  — registered via 'api.version:v1' middleware
|               Bump to v2 by duplicating the outer group + changing prefix.
| Idempotency:  All authenticated POST requests require an Idempotency-Key
|               header. Retries with the same key replay the original result.
| Middleware:   ministry.admin (level 1) | zone.admin (level 2) | church.admin (level 3)
|               activity.logger | rate.limit:{type} | cache.response | idempotency
|
*/

// =============================================================================
// v1  —  All routes live under /api/v1
// =============================================================================
Route::prefix('v1')
     ->middleware('api.version:v1')
     ->group(function () {

    // =========================================================================
    // PUBLIC — No authentication required
    // (idempotency is NOT applied here; login/reset are inherently idempotent
    //  or already guarded by rate-limiting)
    // =========================================================================
    Route::prefix('auth')->group(function () {

        Route::post('login',                [AuthController::class,          'login'])
             ->middleware('rate.limit:auth');

        Route::post('forgot-password',      [PasswordResetController::class, 'sendResetLink'])
             ->middleware('rate.limit:auth');

        Route::post('reset-password',       [PasswordResetController::class, 'resetPassword'])
             ->middleware('rate.limit:auth');

        Route::get('reset-password/verify', [PasswordResetController::class, 'verifyToken']);
    });


    // =========================================================================
    // AUTHENTICATED — Requires valid Sanctum token
    //
    // 'idempotency' is last in the chain so it only runs after auth succeeds.
    // The middleware self-skips GET/PUT/DELETE and the exempt auth sub-paths
    // (logout, refresh), so adding it here covers every POST automatically.
    // =========================================================================
    Route::middleware([
            'auth:sanctum',
            'church.admin',
            'activity.logger',
            'rate.limit:api',
            'idempotency',          // ← prevents duplicate POST submissions
         ])
         ->group(function () {

        // -------------------------------------------------------------------
        // Auth
        // -------------------------------------------------------------------
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);   // exempt in IdempotencyMiddleware
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('profile',          [AuthController::class, 'updateProfile']);
            Route::put('change-password',  [AuthController::class, 'changePassword']);
            Route::post('refresh',         [AuthController::class, 'refresh']);  // exempt in IdempotencyMiddleware
        });


        // -------------------------------------------------------------------
        // Transaction Types — all admins can view; only ministry can manage
        // -------------------------------------------------------------------
        Route::get('transaction-types',                       [TransactionTypeController::class, 'index']);
        Route::get('transaction-types/{transactionType}',     [TransactionTypeController::class, 'show']);

        Route::middleware('ministry.admin')->group(function () {
            Route::post('transaction-types',                  [TransactionTypeController::class, 'store']);
            Route::put('transaction-types/{transactionType}', [TransactionTypeController::class, 'update']);
            Route::delete('transaction-types/{transactionType}', [TransactionTypeController::class, 'destroy']);
        });


        // -------------------------------------------------------------------
        // Transactions — core financial recording (all admins, scoped by role)
        // -------------------------------------------------------------------
        Route::get('transactions',                       [TransactionController::class, 'index']);
        Route::post('transactions',                      [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}',         [TransactionController::class, 'show']);
        Route::put('transactions/{transaction}',         [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}',      [TransactionController::class, 'destroy']);
        Route::post('transactions/{transaction}/verify', [TransactionController::class, 'verify']);

        Route::middleware('ministry.admin')->group(function () {
            Route::post('transactions/{transaction}/unverify', [TransactionController::class, 'unverify']);
        });


        // -------------------------------------------------------------------
        // Members — all admins (scoped by role)
        // -------------------------------------------------------------------
        Route::apiResource('members', MemberController::class);


        // -------------------------------------------------------------------
        // Churches — all admins view; create/delete restricted to zone admin
        // -------------------------------------------------------------------
        Route::get('churches',          [ChurchController::class, 'index']);
        Route::get('churches/{church}', [ChurchController::class, 'show']);
        Route::put('churches/{church}', [ChurchController::class, 'update']);

        Route::middleware('zone.admin')->group(function () {
            Route::post('churches',             [ChurchController::class, 'store']);
            Route::delete('churches/{church}',  [ChurchController::class, 'destroy']);
        });


        // -------------------------------------------------------------------
        // Zones — zone admin reads; ministry admin full CRUD
        // -------------------------------------------------------------------
        Route::middleware('zone.admin')->group(function () {
            Route::get('zones',        [ZoneController::class, 'index']);
            Route::get('zones/{zone}', [ZoneController::class, 'show']);
        });

        Route::middleware('ministry.admin')->group(function () {
            Route::post('zones',            [ZoneController::class, 'store']);
            Route::put('zones/{zone}',      [ZoneController::class, 'update']);
            Route::delete('zones/{zone}',   [ZoneController::class, 'destroy']);
        });


        // -------------------------------------------------------------------
        // Ministry Profile — ministry admin only
        // -------------------------------------------------------------------
        Route::middleware('ministry.admin')->group(function () {
            Route::get('ministry', [MinistryController::class, 'show']);
            Route::put('ministry', [MinistryController::class, 'update']);
        });


        // -------------------------------------------------------------------
        // User Management — ministry admin only
        // -------------------------------------------------------------------
        Route::middleware('ministry.admin')->group(function () {
            Route::get('users',                       [UserController::class, 'index']);
            Route::post('users',                      [UserController::class, 'store']);
            Route::get('users/{user}',                [UserController::class, 'show']);
            Route::put('users/{user}',                [UserController::class, 'update']);
            Route::delete('users/{user}',             [UserController::class, 'destroy']);
            Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        });


        // -------------------------------------------------------------------
        // Analytics — scoped by role + cached
        // -------------------------------------------------------------------
        Route::prefix('analytics')
             ->middleware(['cache.response:1800', 'rate.limit:analytics'])
             ->group(function () {

            // Church-level — all admins (scoped by role)
            Route::prefix('church')->group(function () {
                Route::get('summary',     [ChurchAnalyticsController::class, 'summary']);
                Route::get('trend',       [ChurchAnalyticsController::class, 'trend']);
                Route::get('top-members', [ChurchAnalyticsController::class, 'topMembers']);
            });

            // Zone-level — zone admin and above
            Route::prefix('zone')
                 ->middleware('zone.admin')
                 ->group(function () {
                Route::get('summary',             [ZoneAnalyticsController::class, 'summary']);
                Route::get('churches-comparison', [ZoneAnalyticsController::class, 'churchesComparison']);
                Route::get('trend',               [ZoneAnalyticsController::class, 'trend']);
            });

            // Ministry-level — ministry admin only
            Route::prefix('ministry')
                 ->middleware('ministry.admin')
                 ->group(function () {
                Route::get('overview',         [MinistryAnalyticsController::class, 'overview']);
                Route::get('summary',          [MinistryAnalyticsController::class, 'summary']);
                Route::get('zones-comparison', [MinistryAnalyticsController::class, 'zonesComparison']);
                Route::get('top-churches',     [MinistryAnalyticsController::class, 'topChurches']);
                Route::get('trend',            [MinistryAnalyticsController::class, 'trend']);
            });
        });


        // -------------------------------------------------------------------
        // Activity Logs — all admins (scoped by role, short cache)
        // -------------------------------------------------------------------
        Route::prefix('activity-logs')
             ->middleware('cache.response:300')
             ->group(function () {
            Route::get('/',     [ActivityLogController::class, 'index']);
            Route::get('stats', [ActivityLogController::class, 'stats']);
            Route::get('usage', [ActivityLogController::class, 'usage']);
        });

    }); // end authenticated group

}); // end v1
