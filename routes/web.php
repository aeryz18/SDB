<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DryBoxController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// ── Public: Landing page + PWA offline fallback ───────────────────
Route::get('/', fn() => view('welcome'))->name('landing');
Route::get('/offline', fn() => view('offline'))->name('offline');

// ── Guest-only: Auth pages ────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// ── Demo Mode (no auth required) ──────────────────────────────
Route::get('/demo',           [App\Http\Controllers\DemoController::class, 'enter'])->name('demo.enter');
Route::get('/demo/exit',      [App\Http\Controllers\DemoController::class, 'exit'])->name('demo.exit');
Route::get('/demo/dashboard', [App\Http\Controllers\DemoController::class, 'dashboard'])->name('demo.dashboard');
Route::get('/demo/equipment', [App\Http\Controllers\DemoController::class, 'equipment'])->name('demo.equipment');
Route::get('/demo/analytics', [App\Http\Controllers\DemoController::class, 'analytics'])->name('demo.analytics');
Route::get('/demo/settings',  [App\Http\Controllers\DemoController::class, 'settings'])->name('demo.settings');

// ── Auth: Logout ──────────────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Protected: App routes ─────────────────────────────────────────
Route::middleware('auth')->group(function () {
    // ── Onboarding setup wizard ───────────────────────────────────
    Route::get('/setup',                    [SetupController::class, 'wizard'])->name('setup');
    Route::post('/setup/device',            [SetupController::class, 'storeDevice'])->name('setup.device.store');
    Route::get('/setup/firmware/{device}',  [SetupController::class, 'firmware'])->name('setup.firmware');
    Route::get('/setup/alerts',             [SetupController::class, 'alerts'])->name('setup.alerts');

    Route::get('/dashboard', [DryBoxController::class, 'dashboard'])->name('dashboard');
    Route::get('/equipment', [DryBoxController::class, 'equipment'])->name('equipment');
    Route::get('/analytics', [DryBoxController::class, 'analytics'])->name('analytics');
    Route::get('/settings',  [DryBoxController::class, 'settings'])->name('settings');
    Route::post('/settings', [DryBoxController::class, 'saveSettings'])->name('settings.save');

    Route::get('/report',    [App\Http\Controllers\ReportController::class, 'generate'])->name('report.generate');

    Route::post('/devices',                       [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('/devices/{device}',            [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/devices/{device}/silica',       [DeviceController::class, 'markSilicaReplaced'])->name('devices.silica');
    Route::post('/devices/{device}/protection',   [DeviceController::class, 'toggleProtection'])->name('devices.protection');
});
