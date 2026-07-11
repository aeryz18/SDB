@extends('layouts.app')

@section('title', 'DryBox AI - Dashboard')

@php
$warnThresh = $settings?->warn_humidity ?? 35;
$critThresh = $settings?->crit_humidity ?? 45;
$firebasePath = $device?->firebase_path ?? 'drybox';

$protected = $settings?->protection_mode ?? false;

// Silica gel status (computed by App\Services\SilicaStatus)
$silicaReplaced  = $silica['replaced'];
$silicaInterval  = $silica['interval'];
$silicaDaysSince = $silica['days_since'];
$silicaDaysLeft  = $silica['days_left'];
$silicaDue       = $silica['due'];
$silicaWarning   = $silica['warning'];
$silicaBarPct    = $silica['bar_pct'];
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-md">

@if(!$device)
{{-- ══════════════════════════════════════════════════════════════
     EMPTY STATE — no device set up yet
════════════════════════════════════════════════════════════════ --}}
<div class="flex flex-col items-center justify-center py-20 text-center">
    <div class="w-20 h-20 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center mb-6">
        <span class="material-symbols-outlined text-blue-500" style="font-size:40px">sensors_off</span>
    </div>
    <h1 class="font-display font-bold text-2xl text-slate-800 mb-2">No DryBox unit connected</h1>
    <p class="text-slate-500 max-w-sm mb-8">
        Complete the setup wizard to register your device, flash the ESP32 firmware, and start live monitoring.
    </p>
    <div class="flex flex-col sm:flex-row gap-3">
        <a href="{{ route('setup') }}"
           class="px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined" style="font-size:18px">rocket_launch</span>
            Start Setup Wizard
        </a>
        <a href="{{ route('device') }}"
           class="px-8 py-3.5 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl font-semibold text-sm transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            Add Device Manually
        </a>
    </div>

    {{-- Setup checklist --}}
    <div class="mt-12 bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md text-left">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Setup checklist</p>
        <div class="space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-emerald-600" style="font-size:14px">check</span>
                </div>
                <span class="text-sm text-slate-600">Create your account</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-slate-400">2</span>
                </div>
                <span class="text-sm text-slate-400">Register your DryBox device</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-slate-400">3</span>
                </div>
                <span class="text-sm text-slate-400">Flash ESP32 firmware</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-slate-400">4</span>
                </div>
                <span class="text-sm text-slate-400">Enable email alerts (optional)</span>
            </div>
        </div>
    </div>
</div>

@else
{{-- ══════════════════════════════════════════════════════════════
     NORMAL DASHBOARD — device is connected
════════════════════════════════════════════════════════════════ --}}

    {{-- ── Page Header ───────────────────────────────────────────── --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">System Dashboard</h1>
            <p class="font-body-base text-body-base text-on-surface-variant">
                Live monitoring —
                <span class="font-semibold text-on-surface">{{ $device->name }}</span>
                @if($device->location)
                    <span class="text-slate-400"> · {{ $device->location }}</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3 self-start md:self-auto flex-wrap">
            {{-- Protection badge --}}
            <span class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold border {{ $protected ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-50 text-slate-500 border-outline-variant' }}">
                <span class="material-symbols-outlined" style="font-size:16px">{{ $protected ? 'security' : 'lock_open' }}</span>
                {{ $protected ? 'Armed' : 'Disarmed' }}
            </span>

            {{-- Connection Status Badge --}}
            <div class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-outline-variant rounded-xl">
                <div class="w-2.5 h-2.5 rounded-full bg-slate-300 animate-pulse" id="live-dot"></div>
                <span class="text-sm font-semibold text-slate-500" id="connection-status">Connecting…</span>
            </div>
        </div>
    </div>

    {{-- ── Silica Gel Login Alert ──────────────────────────────── --}}
    {{-- Server-rendered from $silica (no JS/session flag needed) — re-evaluates
         on every dashboard load, so it keeps appearing until marked replaced. --}}
    @if($silica['warning'] || $silica['due'])
    <section class="{{ $silica['due'] ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }} border rounded-xl p-md flex items-start gap-4">
        <div class="p-3 {{ $silica['due'] ? 'bg-red-500' : 'bg-amber-500' }} rounded-full text-white flex-shrink-0">
            <span class="material-symbols-outlined">science</span>
        </div>
        <div class="flex-1">
            <h3 class="font-headline-md {{ $silica['due'] ? 'text-red-800' : 'text-amber-800' }}">
                @if($silica['due'])
                    Silica gel replacement overdue by {{ abs($silica['days_left']) }}d — replace now
                @else
                    Silica gel replacement due in {{ $silica['days_left'] }}d
                @endif
            </h3>
            <p class="font-body-sm {{ $silica['due'] ? 'text-red-600' : 'text-amber-600' }} mt-1">
                <a href="{{ route('silica.log') }}" class="underline font-semibold">View silica log</a>
            </p>
        </div>
    </section>
    @endif

    {{-- ── Dynamic Alert Banner ────────────────────────────────── --}}
    <section id="alert-banner" class="hidden" aria-live="polite"></section>

    {{-- ── Live Sensor Stat Cards ──────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-grid-gutter">

        {{-- Temperature --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">TEMPERATURE</span>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="font-data-num text-data-num text-on-background leading-none transition-all" id="temp-value">--</span>
                        <span class="text-2xl font-semibold text-outline mb-1">°C</span>
                    </div>
                </div>
                <div class="p-3 bg-orange-50 rounded-xl group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-orange-500">thermostat</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center gap-1.5 text-xs text-on-surface-variant">
                <span class="material-symbols-outlined" style="font-size:14px">schedule</span>
                Updated: <span id="temp-time">--</span>
            </div>
        </div>

        {{-- Humidity --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">HUMIDITY</span>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="font-data-num text-data-num text-on-background leading-none transition-all" id="hum-value">--</span>
                        <span class="text-2xl font-semibold text-outline mb-1">%</span>
                    </div>
                </div>
                <div class="p-3 bg-blue-50 rounded-xl group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-blue-500">water_drop</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100">
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 transition-all duration-1000 rounded-full" id="hum-bar" style="width:0%"></div>
                </div>
                <p class="text-xs text-on-surface-variant mt-1">Relative Humidity Level</p>
            </div>
        </div>

        {{-- Status --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">STATUS</span>
                    <div class="mt-2">
                        <span class="font-headline-md text-headline-md text-on-surface" id="status-value">--</span>
                    </div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl group-hover:scale-110 transition-transform" id="status-icon-wrap">
                    <span class="material-symbols-outlined text-slate-400" id="status-icon">sensors</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-slate-300" id="status-dot"></div>
                <span class="text-xs text-on-surface-variant" id="status-sub">Waiting for data…</span>
            </div>
        </div>

    </section>

    {{-- ── Silica Gel + Protection Row ─────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-2 gap-grid-gutter">

        {{-- Silica Gel Status --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">SILICA GEL</span>
                    <div class="flex items-center gap-3 mt-2">
                        @if(!$silicaReplaced)
                            <span class="font-headline-md text-xl font-bold text-slate-400">Unknown</span>
                            <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-slate-50 text-slate-500 border border-slate-200">Not logged</span>
                        @elseif($silicaDue)
                            <span class="font-headline-md text-xl font-bold text-red-600">Overdue</span>
                            <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-red-50 text-red-700 border border-red-200">Replace now</span>
                        @elseif($silicaWarning)
                            <span class="font-headline-md text-xl font-bold text-amber-600">{{ $silicaDaysLeft }}d left</span>
                            <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Replace soon</span>
                        @else
                            <span class="font-headline-md text-xl font-bold text-emerald-600">{{ $silicaDaysLeft }}d left</span>
                            <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">OK</span>
                        @endif
                    </div>
                </div>
                <div class="p-3 {{ $silicaDue ? 'bg-red-50' : ($silicaWarning ? 'bg-amber-50' : 'bg-emerald-50') }} rounded-xl">
                    <span class="material-symbols-outlined {{ $silicaDue ? 'text-red-500' : ($silicaWarning ? 'text-amber-500' : 'text-emerald-500') }}">science</span>
                </div>
            </div>

            {{-- Gel life progress bar --}}
            @if($silicaReplaced)
            <div class="mb-4">
                <div class="flex justify-between text-xs text-slate-400 mb-1.5">
                    <span>Gel life used</span>
                    <span>{{ $silicaDaysSince }}d / {{ $silicaInterval }}d</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $silicaDue ? 'bg-red-500' : ($silicaWarning ? 'bg-amber-500' : 'bg-emerald-500') }} rounded-full transition-all duration-700"
                         style="width:{{ $silicaBarPct }}%"></div>
                </div>
                <div class="flex justify-between text-[10px] text-slate-300 mt-1">
                    <span>Replaced</span><span>Warning</span><span>Due</span>
                </div>
            </div>
            @endif

            {{-- Alert message --}}
            @if($silicaDue || $silicaWarning || !$silicaReplaced)
            <div class="mb-3">
                <div class="flex items-start gap-1.5 text-xs {{ $silicaDue ? 'text-red-600' : ($silicaWarning ? 'text-amber-600' : 'text-slate-400') }}">
                    <span class="material-symbols-outlined flex-shrink-0" style="font-size:13px;margin-top:1px">arrow_right</span>
                    <span>
                        @if(!$silicaReplaced)
                            No replacement has been logged yet for this device.
                        @elseif($silicaDue)
                            {{ abs($silicaDaysLeft) }} day(s) overdue — gel may be saturated and no longer absorbing moisture effectively.
                        @elseif($silicaWarning)
                            {{ $silicaDaysLeft }} days until scheduled replacement (every {{ $silicaInterval }} days).
                        @endif
                    </span>
                </div>
            </div>
            @endif

            <div class="pt-3 border-t border-slate-100">
                <a href="{{ route('silica.log') }}" class="text-xs text-primary font-semibold flex items-center gap-1 hover:underline">
                    <span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>
                    View full silica log
                </a>
            </div>
        </div>

        {{-- Protection Mode Status --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">PROTECTION MODE</span>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="font-headline-md text-xl font-bold {{ $protected ? 'text-purple-600' : 'text-slate-400' }}">
                            {{ $protected ? 'Armed' : 'Disarmed' }}
                        </span>
                    </div>
                </div>
                <div class="p-3 {{ $protected ? 'bg-purple-50' : 'bg-slate-50' }} rounded-xl">
                    <span class="material-symbols-outlined {{ $protected ? 'text-purple-500' : 'text-slate-400' }}">security</span>
                </div>
            </div>

            <div class="space-y-2 mb-4">
                <div class="flex items-center gap-2 text-sm {{ $protected ? 'text-purple-700' : 'text-slate-400' }}">
                    <span class="material-symbols-outlined" style="font-size:16px">{{ $protected ? 'notifications_active' : 'notifications_off' }}</span>
                    {{ $protected ? 'Tamper alerts active — door open triggers email' : 'No tamper alerts — box can be opened freely' }}
                </div>
                @if($device)
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="material-symbols-outlined" style="font-size:14px">sensors</span>
                    Monitoring: <code class="bg-slate-100 px-1 rounded">{{ $device->firebase_path }}/{{ $settings?->door_field ?? 'door' }}</code>
                </div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100">
                <a href="{{ route('device') }}" class="text-xs text-primary font-semibold flex items-center gap-1 hover:underline">
                    <span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>
                    Change on Device page
                </a>
            </div>
        </div>

    </section>

    {{-- ── Real-Time Humidity Chart ─────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-md">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Live Humidity Trend</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Real-time readings from Firebase (last 20 points)
                    — Warn: {{ $warnThresh }}% / Crit: {{ $critThresh }}%
                </p>
            </div>
            <span class="flex items-center gap-2 text-xs text-emerald-600 font-bold uppercase tracking-wider bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                Live
            </span>
        </div>
        <div class="relative h-64">
            <canvas id="humidity-chart"></canvas>
        </div>
    </section>

    {{-- ── Connection Footer ────────────────────────────────── --}}
    <section class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-surface-container-low border border-outline-variant rounded-xl p-md gap-4">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary">cloud_sync</span>
            <div>
                <span class="font-label-caps text-label-caps text-on-surface-variant block">FIREBASE REALTIME DATABASE</span>
                <span class="font-body-sm text-body-sm text-on-surface font-mono text-xs">
                    {{ parse_url(config('firebase.database_url'), PHP_URL_HOST) }}/{{ $firebasePath }}/
                </span>
            </div>
        </div>
        <div class="text-left sm:text-right">
            <span class="font-label-caps text-label-caps text-on-surface-variant block">LAST READING</span>
            <span class="font-body-sm text-body-sm text-on-surface font-semibold" id="last-update-time">--</span>
        </div>
    </section>

@endif {{-- end @if($device) --}}

</div>
@endsection

@if($device)
@section('scripts')
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
// ── Firebase Init ─────────────────────────────────────────────
const firebaseConfig = {
    apiKey:      "{{ config('firebase.api_key') }}",
    databaseURL: "{{ config('firebase.database_url') }}",
};
if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
const db = firebase.database();

// ── Thresholds from server (device_settings table) ────────────
const CRIT_THRESH = {{ $critThresh }};
const WARN_THRESH = {{ $warnThresh }};

// ── DOM refs ──────────────────────────────────────────────────
const tempEl         = document.getElementById('temp-value');
const tempTimeEl     = document.getElementById('temp-time');
const humEl          = document.getElementById('hum-value');
const humBar         = document.getElementById('hum-bar');
const statusEl       = document.getElementById('status-value');
const statusDot      = document.getElementById('status-dot');
const statusSub      = document.getElementById('status-sub');
const statusIcon     = document.getElementById('status-icon');
const statusIconWrap = document.getElementById('status-icon-wrap');
const lastUpdateEl   = document.getElementById('last-update-time');
const connStatusEl   = document.getElementById('connection-status');
const liveDot        = document.getElementById('live-dot');
const alertBanner    = document.getElementById('alert-banner');

// ── Chart.js Setup ────────────────────────────────────────────
const ctx = document.getElementById('humidity-chart').getContext('2d');
const humidityChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Humidity (%)',
            data: [],
            borderColor: '#0061a4',
            backgroundColor: 'rgba(0,97,164,0.07)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#0061a4',
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2.5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y.toFixed(1)} %` } },
            annotation: undefined,
        },
        scales: {
            y: {
                min: 0, max: 100,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { callback: v => v + '%', font: { family: 'Inter', size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { font: { family: 'Inter', size: 10 }, maxTicksLimit: 8 }
            }
        },
        animation: { duration: 400 }
    }
});

// ── Status Helper ─────────────────────────────────────────────
function applyStatus(statusStr, humidity) {
    const s = (statusStr || '').toLowerCase();
    const isCritical = s.includes('critical') || humidity > CRIT_THRESH;
    const isWarning  = s.includes('warning')  || (humidity > WARN_THRESH && !isCritical);

    statusEl.textContent = statusStr || 'Unknown';

    if (isCritical) {
        statusEl.className      = 'font-headline-md text-headline-md text-red-600';
        statusDot.className     = 'w-2 h-2 rounded-full bg-red-500 animate-pulse';
        statusIcon.textContent  = 'crisis_alert';
        statusIconWrap.className= 'p-3 bg-red-50 rounded-xl';
        statusIcon.className    = 'material-symbols-outlined text-red-500';
        statusSub.textContent   = 'Critical — immediate action required!';
        humBar.className        = 'h-full bg-red-500 transition-all duration-1000 rounded-full';
        showAlert('CRITICAL', true, `Humidity at ${humidity}% — desiccant may be saturated!`);

    } else if (isWarning) {
        statusEl.className      = 'font-headline-md text-headline-md text-amber-600';
        statusDot.className     = 'w-2 h-2 rounded-full bg-amber-500 animate-pulse';
        statusIcon.textContent  = 'warning';
        statusIconWrap.className= 'p-3 bg-amber-50 rounded-xl';
        statusIcon.className    = 'material-symbols-outlined text-amber-500';
        statusSub.textContent   = 'Humidity approaching critical threshold';
        humBar.className        = 'h-full bg-amber-500 transition-all duration-1000 rounded-full';
        showAlert('WARNING', false, `Humidity at ${humidity}% — approaching critical threshold.`);

    } else {
        statusEl.className      = 'font-headline-md text-headline-md text-emerald-600';
        statusDot.className     = 'w-2 h-2 rounded-full bg-emerald-500';
        statusIcon.textContent  = 'check_circle';
        statusIconWrap.className= 'p-3 bg-emerald-50 rounded-xl';
        statusIcon.className    = 'material-symbols-outlined text-emerald-500';
        statusSub.textContent   = 'All conditions within safe limits';
        humBar.className        = 'h-full bg-blue-500 transition-all duration-1000 rounded-full';
        alertBanner.classList.add('hidden');
    }
}

function showAlert(level, isCritical, msg) {
    const bg     = isCritical ? 'bg-red-50 border-red-200'   : 'bg-amber-50 border-amber-200';
    const iconBg = isCritical ? 'bg-red-500'                 : 'bg-amber-500';
    const icon   = isCritical ? 'crisis_alert'               : 'warning';
    const text   = isCritical ? 'text-red-800'               : 'text-amber-800';
    const sub    = isCritical ? 'text-red-600'               : 'text-amber-600';
    alertBanner.classList.remove('hidden');
    alertBanner.innerHTML = `
      <div class="${bg} border rounded-xl p-md flex items-start gap-4">
        <div class="p-3 ${iconBg} rounded-full text-white flex-shrink-0">
          <span class="material-symbols-outlined">${icon}</span>
        </div>
        <div>
          <h3 class="font-headline-md ${text}">${level}: ${document.title.includes('DryBox') ? '{{ $device?->name ?? "Sensor" }}' : 'Sensor'}</h3>
          <p class="font-body-sm ${sub} mt-1">${msg}</p>
        </div>
      </div>`;
}

// ── Firebase Connection State ─────────────────────────────────
db.ref('.info/connected').on('value', snap => {
    if (snap.val() === true) {
        connStatusEl.textContent = 'Live';
        connStatusEl.className   = 'text-sm font-semibold text-emerald-700';
        liveDot.className        = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
    } else {
        connStatusEl.textContent = 'Reconnecting…';
        connStatusEl.className   = 'text-sm font-semibold text-amber-600';
        liveDot.className        = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
    }
});

// ── Main Sensor Listener ──────────────────────────────────────
db.ref("{{ $firebasePath }}").on("value", snapshot => {
    const data = snapshot.val();
    if (!data) return;

    const now  = new Date().toLocaleTimeString();
    const temp = parseFloat(data.temperature ?? 0);
    const hum  = parseFloat(data.humidity    ?? 0);

    tempEl.textContent       = temp.toFixed(1);
    tempTimeEl.textContent   = now;
    humEl.textContent        = hum.toFixed(1);
    humBar.style.width       = Math.min(hum, 100) + '%';
    lastUpdateEl.textContent = now;

    applyStatus(data.status, hum);

    humidityChart.data.labels.push(now);
    humidityChart.data.datasets[0].data.push(hum);
    if (humidityChart.data.labels.length > 20) {
        humidityChart.data.labels.shift();
        humidityChart.data.datasets[0].data.shift();
    }
    humidityChart.update();
});
</script>
@endsection
@endif {{-- end @if($device) scripts guard --}}
