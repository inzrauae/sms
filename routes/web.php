<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ pages */

Route::get('/', [PageController::class, 'home']);
Route::get('/docs', [PageController::class, 'docs']);
Route::get('/login', [PageController::class, 'authPage']);
Route::get('/register', [PageController::class, 'authPage']);

Route::get('/config', [SystemController::class, 'config']);
Route::get('/healthz', [SystemController::class, 'health']);

// Server-side guards so a signed-out visitor never sees dashboard chrome.
Route::get('/dashboard', [PageController::class, 'dashboard'])->middleware(['auth', 'active']);
Route::get('/console', [PageController::class, 'console'])->middleware(['auth', 'active', 'admin']);

/* ------------------------------------------------------------------- auth */

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-attempts');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-attempts');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/session', [AuthController::class, 'session']);
});

/* -------------------------------------------------------------- dashboard */

Route::prefix('app')->middleware(['auth', 'active'])->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::post('/preview', [DashboardController::class, 'preview']);
    Route::post('/send', [DashboardController::class, 'send']);
    Route::post('/campaign', [DashboardController::class, 'campaign']);

    Route::get('/messages', [DashboardController::class, 'messages']);
    Route::get('/messages/export', [DashboardController::class, 'messagesExport']);
    Route::post('/messages/sync', [DashboardController::class, 'messagesSync']);
    Route::get('/campaigns', [DashboardController::class, 'campaigns']);

    Route::get('/senders', [DashboardController::class, 'sendersIndex']);
    Route::post('/senders', [DashboardController::class, 'sendersStore']);
    Route::delete('/senders/{id}', [DashboardController::class, 'sendersDestroy']);

    Route::get('/groups', [DashboardController::class, 'groupsIndex']);
    Route::post('/groups', [DashboardController::class, 'groupsStore']);
    Route::patch('/groups/{groupUid}', [DashboardController::class, 'groupsUpdate']);
    Route::delete('/groups/{groupUid}', [DashboardController::class, 'groupsDestroy']);

    Route::get('/groups/{groupUid}/contacts', [DashboardController::class, 'contactsIndex']);
    Route::post('/groups/{groupUid}/contacts', [DashboardController::class, 'contactsStore']);
    Route::post('/groups/{groupUid}/import', [DashboardController::class, 'contactsImport']);
    Route::delete('/groups/{groupUid}/contacts/{contactUid}', [DashboardController::class, 'contactsDestroy']);

    Route::get('/tokens', [DashboardController::class, 'tokensIndex']);
    Route::post('/tokens', [DashboardController::class, 'tokensStore']);
    Route::delete('/tokens/{id}', [DashboardController::class, 'tokensDestroy']);

    Route::get('/transactions', [DashboardController::class, 'transactions']);
    Route::post('/topup-request', [DashboardController::class, 'topupRequest']);

    Route::patch('/profile', [DashboardController::class, 'updateProfile']);
    Route::post('/password', [DashboardController::class, 'updatePassword']);
});

/* ------------------------------------------------------------------ admin */

Route::prefix('admin')->middleware(['auth', 'active', 'admin'])->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);

    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users/{id}/credits', [AdminController::class, 'usersCredits']);
    Route::patch('/users/{id}', [AdminController::class, 'usersUpdate']);

    Route::get('/senders', [AdminController::class, 'sendersIndex']);
    Route::post('/senders/{id}/decision', [AdminController::class, 'sendersDecision']);

    Route::get('/messages', [AdminController::class, 'messages']);
    Route::get('/requests', [AdminController::class, 'requests']);

    Route::post('/settings', [AdminController::class, 'settingsStore']);
});
