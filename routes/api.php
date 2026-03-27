<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth')->group(function () {
    // User endpoint
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Sync endpoints
    Route::prefix('/sync')->group(function () {
        Route::get('/companies', [SyncController::class, 'syncCompanies']);
        Route::get('/accounts', [SyncController::class, 'syncAccounts']);
        Route::get('/policies', [SyncController::class, 'syncPolicies']);
        Route::post('/batch', [SyncController::class, 'batchSync']);
    });
});

