<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DryBoxController;
use Illuminate\Support\Facades\Route;

// ── Public: Landing page ──────────────────────────────────────────
Route::get('/', fn() => view('welcome'))->name('landing');

// ── Guest-only: Auth pages ────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);
});

// ── Auth: Logout ──────────────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Protected: App routes ─────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DryBoxController::class, 'dashboard'])->name('dashboard');
    Route::get('/equipment', [DryBoxController::class, 'equipment'])->name('equipment');
    Route::get('/analytics', [DryBoxController::class, 'analytics'])->name('analytics');
    Route::get('/settings',  [DryBoxController::class, 'settings'])->name('settings');
});
