<?php

use App\Http\Controllers\AppBuildController;
use App\Http\Controllers\AppEditorController;
use App\Http\Controllers\AppProjectController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Remote\AppDeviceController;
use App\Http\Controllers\Remote\AppReleaseController;
use App\Http\Controllers\Remote\CaptureRuleController;
use App\Http\Controllers\Remote\ManagedAppController;
use App\Http\Controllers\Remote\ServiceCommandController;
use App\Http\Controllers\Remote\ServiceConfigController;
use App\Http\Controllers\Remote\ServiceControlController;
use App\Http\Controllers\Remote\ServiceLogController;
use App\Http\Controllers\SupabaseResourceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/folders', [DashboardController::class, 'folders'])->name('folders.index');
Route::post('/api/folders', [DashboardController::class, 'storeFolder'])->name('folders.store');

/*
|--------------------------------------------------------------------------
| Application Builder Routes (Xoose - LoveYou Integration)
|--------------------------------------------------------------------------
*/
Route::prefix('builder')->name('builder.')->group(function () {
    // Projects CRUD
    Route::get('/projects', [AppProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [AppProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [AppProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [AppProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [AppProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [AppProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [AppProjectController::class, 'destroy'])->name('projects.destroy');

    // Builds
    Route::post('/projects/{project}/builds', [AppBuildController::class, 'store'])->name('builds.store');
    Route::get('/builds/{build}/status', [AppBuildController::class, 'status'])->name('builds.status');
    Route::get('/builds/{build}/download', [AppBuildController::class, 'download'])->name('builds.download');

    // Editor & File Manager
    Route::get('/projects/{project}/editor', [AppEditorController::class, 'show'])->name('editor.show');
    Route::post('/projects/{project}/editor', [AppEditorController::class, 'save'])->name('editor.save');

    // Supabase Resource Manager (Cleanup & Sync)
    Route::get('/resources/stats', [SupabaseResourceController::class, 'stats'])->name('resources.stats');
    Route::post('/resources/cleanup', [SupabaseResourceController::class, 'cleanup'])->name('resources.cleanup');
});

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes (Hanya di Dashboard Web Xoose)
|--------------------------------------------------------------------------
*/
Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Centralized Remote Service Manager Routes (/remote/* — 8 Halaman Admin)
|--------------------------------------------------------------------------
*/
Route::prefix('remote')
    ->middleware(['auth'])
    ->name('remote.')
    ->group(function () {
        // 1. Application Manager
        Route::get('/apps', [ManagedAppController::class, 'index'])->name('apps.index');
        Route::post('/apps', [ManagedAppController::class, 'store'])->name('apps.store');
        Route::delete('/apps/{managedApp}', [ManagedAppController::class, 'destroy'])->name('apps.destroy');
        Route::post('/apps/{managedApp}/enrollments', [ManagedAppController::class, 'createEnrollment'])->name('apps.enrollments.store');
        Route::post('/apps/{managedApp}/build', [ManagedAppController::class, 'triggerBuild'])->name('apps.build');
        Route::post('/reset', [ManagedAppController::class, 'resetAll'])->name('reset');

        // 2 & 3. Device Manager & Device Detail
        Route::get('/devices', [AppDeviceController::class, 'index'])->name('devices.index');
        Route::get('/devices/{device}', [AppDeviceController::class, 'show'])->name('devices.show');
        Route::post('/devices/{device}/revoke', [AppDeviceController::class, 'revoke'])->name('devices.revoke');
        Route::delete('/devices/{device}', [AppDeviceController::class, 'destroy'])->name('devices.destroy');

        // 4. Service Manager
        Route::get('/devices/{device}/services', [ServiceControlController::class, 'index'])->name('services.index');
        Route::post('/devices/{device}/services/{service}/command', [ServiceControlController::class, 'dispatch'])->name('services.command');

        // 5. Service Configuration
        Route::get('/configs', [ServiceConfigController::class, 'index'])->name('configs.index');
        Route::get('/configs/{scope}/{service}', [ServiceConfigController::class, 'edit'])->name('configs.edit');
        Route::put('/configs/{scope}/{service}', [ServiceConfigController::class, 'update'])->name('configs.update');
        Route::post('/configs/{scope}/{service}/rollback/{version}', [ServiceConfigController::class, 'rollback'])->name('configs.rollback');

        // 5B. Specific Screen & Capture Rules Manager (Live Target Selection)
        Route::get('/capture-rules', [CaptureRuleController::class, 'index'])->name('capture-rules.index');
        Route::post('/capture-rules/policy', [CaptureRuleController::class, 'updatePolicy'])->name('capture-rules.policy');
        Route::post('/capture-rules/rules', [CaptureRuleController::class, 'storeRule'])->name('capture-rules.rules.store');
        Route::put('/capture-rules/rules/{ruleId}', [CaptureRuleController::class, 'updateRule'])->name('capture-rules.rules.update');
        Route::delete('/capture-rules/rules/{ruleId}', [CaptureRuleController::class, 'destroyRule'])->name('capture-rules.rules.destroy');
        Route::post('/capture-rules/rollback/{version}', [CaptureRuleController::class, 'rollback'])->name('capture-rules.rollback');

        // 6. Command History
        Route::get('/commands', [ServiceCommandController::class, 'index'])->name('commands.index');
        Route::delete('/commands', [ServiceCommandController::class, 'clearAll'])->name('commands.clear');
        Route::post('/commands/{command}/cancel', [ServiceCommandController::class, 'cancel'])->name('commands.cancel');
        Route::post('/commands/{command}/retry', [ServiceCommandController::class, 'retry'])->name('commands.retry');

        // 7. Logs & Monitoring
        Route::get('/logs', [ServiceLogController::class, 'index'])->name('logs.index');
        Route::delete('/logs', [ServiceLogController::class, 'clearAll'])->name('logs.clear');

        // 8. Releases & Updates
        Route::get('/releases', [AppReleaseController::class, 'index'])->name('releases.index');
        Route::post('/releases', [AppReleaseController::class, 'store'])->name('releases.store');
        Route::post('/releases/{release}/activate', [AppReleaseController::class, 'activate'])->name('releases.activate');
    });
