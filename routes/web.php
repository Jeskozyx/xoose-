<?php

use App\Http\Controllers\AppBuildController;
use App\Http\Controllers\AppEditorController;
use App\Http\Controllers\AppProjectController;
use App\Http\Controllers\DashboardController;
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
