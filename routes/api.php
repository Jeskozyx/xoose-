<?php

use App\Http\Controllers\Api\Admin\ControlPlaneApiController;
use App\Http\Controllers\Api\Agent\CommandApiController;
use App\Http\Controllers\Api\Agent\ConfigApiController;
use App\Http\Controllers\Api\Agent\EnrollmentApiController;
use App\Http\Controllers\Api\Agent\HeartbeatApiController;
use App\Http\Controllers\Api\Agent\LogApiController;
use App\Http\Controllers\Api\Agent\ReleaseApiController;
use App\Http\Controllers\Api\Agent\StatusApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LoveYou Device Agent API (/api/v1/agent/*)
|--------------------------------------------------------------------------
| Komunikasi outbound-only dari Windows Client menuju Xoose Control Plane.
| Tidak ada login admin di klien LoveYou — autentikasi berbasis Bearer token
| unik per perangkat yang dapat dicabut (revocable).
*/
Route::prefix('v1/agent')->name('api.agent.')->group(function () {
    // Pendaftaran perangkat baru memakai enrollment_code yang dibuat admin
    Route::post('/enroll', [EnrollmentApiController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('enroll');

    // Endpoint terautentikasi per perangkat (AuthenticateDeviceAgent)
    Route::middleware(['agent.auth', 'throttle:120,1'])->group(function () {
        Route::post('/heartbeat', [HeartbeatApiController::class, 'store'])->name('heartbeat');
        Route::get('/commands/pending', [CommandApiController::class, 'pending'])->name('commands.pending');
        Route::post('/commands/{command}/ack', [CommandApiController::class, 'acknowledge'])->name('commands.ack');
        Route::get('/configs', [ConfigApiController::class, 'index'])->name('configs.index');
        Route::post('/status', [StatusApiController::class, 'store'])->name('status.store');
        Route::post('/logs', [LogApiController::class, 'store'])->name('logs.store');
        Route::get('/releases/latest', [ReleaseApiController::class, 'latest'])->name('releases.latest');
        Route::get('/releases/{release}/download', [ReleaseApiController::class, 'download'])->name('releases.download');
        Route::post('/unregister', [EnrollmentApiController::class, 'revokeSelf'])->name('unregister');
    });
});

/*
|--------------------------------------------------------------------------
| Xoose Admin Control Plane API (/api/v1/admin/*)
|--------------------------------------------------------------------------
| Khusus admin Xoose terautentikasi (session / web guard).
*/
Route::prefix('v1/admin')
    ->middleware(['web', 'auth'])
    ->name('api.admin.')
    ->group(function () {
        Route::get('/apps', [ControlPlaneApiController::class, 'listApps'])->name('apps.index');
        Route::post('/apps', [ControlPlaneApiController::class, 'storeApp'])->name('apps.store');
        Route::post('/apps/{managedApp}/enrollments', [ControlPlaneApiController::class, 'createEnrollment'])->name('apps.enrollments.store');

        Route::get('/devices', [ControlPlaneApiController::class, 'listDevices'])->name('devices.index');
        Route::get('/devices/{device}', [ControlPlaneApiController::class, 'showDevice'])->name('devices.show');
        Route::post('/devices/{device}/revoke', [ControlPlaneApiController::class, 'revokeDevice'])->name('devices.revoke');
        Route::post('/devices/{device}/commands', [ControlPlaneApiController::class, 'dispatchCommand'])->name('devices.commands.store');

        Route::get('/commands', [ControlPlaneApiController::class, 'listCommands'])->name('commands.index');
        Route::post('/commands/{command}/cancel', [ControlPlaneApiController::class, 'cancelCommand'])->name('commands.cancel');

        Route::get('/configs/{managedApp}/{serviceKey}', [ControlPlaneApiController::class, 'listConfigs'])->name('configs.index');
        Route::put('/configs/{managedApp}/{serviceKey}', [ControlPlaneApiController::class, 'updateConfig'])->name('configs.update');
        Route::post('/configs/{managedApp}/{serviceKey}/rollback/{version}', [ControlPlaneApiController::class, 'rollbackConfig'])->name('configs.rollback');

        Route::get('/logs', [ControlPlaneApiController::class, 'listLogs'])->name('logs.index');

        Route::get('/apps/{managedApp}/releases', [ControlPlaneApiController::class, 'listReleases'])->name('releases.index');
        Route::post('/apps/{managedApp}/releases', [ControlPlaneApiController::class, 'storeRelease'])->name('releases.store');
    });
