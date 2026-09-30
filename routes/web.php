<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');
    Route::get('/activities', [DashboardController::class, 'activities'])->name('activities');
    Route::get('/devices', [DashboardController::class, 'devices'])->name('devices');
    Route::get('/devices/{device}', [DashboardController::class, 'deviceDetail'])->name('devices.detail');

    Route::get('/routers', [DashboardController::class, 'routers'])->name('routers');
    Route::post('/routers', [DashboardController::class, 'saveRouter'])->name('routers.save');
    Route::delete('/routers/{router}', [DashboardController::class, 'deleteRouter'])->name('routers.delete');
    Route::post('/routers/test', [DashboardController::class, 'testRouter'])->name('routers.test');
    Route::post('/routers/{router}/sync', [DashboardController::class, 'syncRouter'])->name('routers.sync');
    Route::post('/routers/sync-all', [DashboardController::class, 'syncAllRouters'])->name('routers.sync-all');

    Route::post('/mikrotik/sync', [DashboardController::class, 'syncMikrotik'])->name('mikrotik.sync');
    Route::post('/mikrotik/test', [DashboardController::class, 'testMikrotik'])->name('mikrotik.test');
    Route::post('/settings', [DashboardController::class, 'saveSettings'])->name('settings.save');

    Route::post('/signatures', [DashboardController::class, 'saveSignature'])->name('signatures.save');
    Route::delete('/signatures/{signature}', [DashboardController::class, 'deleteSignature'])->name('signatures.delete');

    Route::post('/simulate', [DashboardController::class, 'simulateLog'])->name('simulate');
});
