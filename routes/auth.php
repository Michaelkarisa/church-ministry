<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')
    ->middleware('api.version:v1')
    ->name('v1.')
    ->group(function () {

        Route::prefix('auth')->name('auth.')->group(function () {


            Route::middleware('rate.limit:auth')->group(function () {

                Route::post('/login', [AuthController::class, 'login'])
                    ->name('login');

                Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
                    ->name('password.forgot');

                Route::get('/reset-password/verify', [PasswordResetController::class, 'verifyToken'])
                    ->name('password.verify');

                Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
                    ->name('password.reset');

            });

  
            Route::middleware(['auth:sanctum', 'rate.limit:api'])->group(function () {

   
                Route::post('/logout', [AuthController::class, 'logout'])
                    ->name('logout');

  
                Route::get('/me', [AuthController::class, 'me'])
                    ->name('me');

                Route::put('/profile', [AuthController::class, 'updateProfile'])
                    ->name('profile.update');

                Route::put('/change-password', [AuthController::class, 'changePassword'])
                    ->name('password.change');

                Route::post('/refresh', [AuthController::class, 'refresh'])
                    ->name('refresh');

            });

        });

    });
