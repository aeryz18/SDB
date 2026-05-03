<?php

use App\Http\Controllers\DryBoxController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DryBoxController::class, 'dashboard'])->name('dashboard');
Route::get('/equipment', [DryBoxController::class, 'equipment'])->name('equipment');
Route::get('/analytics', [DryBoxController::class, 'analytics'])->name('analytics');
Route::get('/settings', [DryBoxController::class, 'settings'])->name('settings');
