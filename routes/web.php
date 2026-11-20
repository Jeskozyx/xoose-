<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/folders', [DashboardController::class, 'folders'])->name('folders.index');
Route::post('/api/folders', [DashboardController::class, 'storeFolder'])->name('folders.store');
