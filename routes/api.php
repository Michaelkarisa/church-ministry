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


Route::prefix('v1')
     ->middleware('api.version:v1')
     ->group(function () {
    Route::middleware([
            'auth:sanctum',
            'church.admin',
            'activity.logger',
            'rate.limit:api',
            'idempotency',          // ← prevents duplicate POST submissions
         ])
         ->group(function () {

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
