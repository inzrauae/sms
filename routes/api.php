<?php

use App\Http\Controllers\Api\SmsApiController;
use Illuminate\Support\Facades\Route;

/**
 * Tenant-facing API, mirroring the Text.lk v3 contract at /api/v3/*.
 * Bearer tokens are Sanctum personal access tokens in `id|secret` form.
 */
Route::prefix('v3')->middleware(['auth:sanctum', 'active', 'throttle:api-token'])->group(function () {
    Route::post('/sms/send', [SmsApiController::class, 'send']);
    Route::post('/sms/campaign', [SmsApiController::class, 'campaign']);
    Route::post('/sms/estimate', [SmsApiController::class, 'estimate']);
    Route::get('/sms/{uid}', [SmsApiController::class, 'show']);
    Route::get('/sms', [SmsApiController::class, 'index']);

    Route::get('/balance', [SmsApiController::class, 'balance']);
    Route::get('/me', [SmsApiController::class, 'me']);
    Route::get('/contacts', [SmsApiController::class, 'contacts']);
});
