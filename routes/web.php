<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DryBoxController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// ── Public: Landing page + PWA offline fallback ───────────────────
Route::get('/', fn () => view('welcome'))->name('landing');
Route::get('/offline', fn () => view('offline'))->name('offline');

// ── Guest-only: Auth pages ────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// ── Demo Mode (no auth required) ──────────────────────────────
Route::get('/demo', [DemoController::class, 'enter'])->name('demo.enter');
Route::get('/demo/exit', [DemoController::class, 'exit'])->name('demo.exit');
Route::get('/demo/dashboard', [DemoController::class, 'dashboard'])->name('demo.dashboard');
Route::get('/demo/equipment', [DemoController::class, 'equipment'])->name('demo.equipment');
Route::get('/demo/analytics', [DemoController::class, 'analytics'])->name('demo.analytics');
Route::get('/demo/settings', [DemoController::class, 'settings'])->name('demo.settings');

// ── Auth: Logout ──────────────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Protected: App routes ─────────────────────────────────────────
Route::middleware('auth')->group(function () {
    // ── Onboarding setup wizard ───────────────────────────────────
    Route::get('/setup', [SetupController::class, 'wizard'])->name('setup');
    Route::post('/setup/device', [SetupController::class, 'storeDevice'])->name('setup.device.store');
    Route::get('/setup/firmware/{device}', [SetupController::class, 'firmware'])->name('setup.firmware');
    Route::get('/setup/alerts', [SetupController::class, 'alerts'])->name('setup.alerts');

    Route::get('/dashboard', [DryBoxController::class, 'dashboard'])->name('dashboard');
    Route::get('/device', [DryBoxController::class, 'device'])->name('device');
    Route::post('/device', [DryBoxController::class, 'saveSettings'])->name('device.save');
    Route::get('/silica-log', [DryBoxController::class, 'silicaLog'])->name('silica.log');
    Route::get('/analytics', [DryBoxController::class, 'analytics'])->name('analytics');

    Route::get('/report', [ReportController::class, 'generate'])->name('report.generate');
    Route::post('/report/email', [ReportController::class, 'emailReport'])->name('report.email');

    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/devices/{device}/silica', [DeviceController::class, 'markSilicaReplaced'])->name('devices.silica');
    Route::delete('/devices/{device}/silica/{replacement}', [DeviceController::class, 'undoSilicaReplacement'])->name('devices.silica.undo');
    Route::post('/devices/{device}/silica-next-replacement', [DeviceController::class, 'updateSilicaNextReplacementAt'])->name('devices.silica.next-replacement');
    Route::post('/devices/{device}/silica-interval', [DeviceController::class, 'updateSilicaInterval'])->name('devices.silica.interval');
    Route::post('/devices/{device}/silica-ai-suggestion', [DeviceController::class, 'getSilicaAiSuggestion'])->name('devices.silica.ai-suggestion');
    Route::post('/devices/{device}/protection', [DeviceController::class, 'toggleProtection'])->name('devices.protection');
});
