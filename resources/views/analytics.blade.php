@extends('layouts.app')

@section('title', 'Analytics & Trends | Incognito')

@php
$warnThresh   = $settings?->warn_humidity ?? 35;
$critThresh   = $settings?->crit_humidity ?? 45;
$firebasePath = $primary?->firebase_path ?? 'drybox';
$deviceName   = $primary?->name ?? 'Dry Box';
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-grid-gutter">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <span class="font-label-caps text-label-caps text-secondary mb-2 block uppercase">Live sensor performance</span>
            <h1 class="font-display-lg text-display-lg text-on-surface tracking-tight">Analytics & Trends</h1>
            @if($primary)
            <p class="text-sm text-slate-500 mt-1">
                {{ $deviceName }}
                @if($primary->location) · {{ $primary->location }} @endif
                <span class="mx-1.5 text-slate-300">·</span>
                <code class="bg-slate-100 px-1 rounded text-xs">{{ $firebasePath }}/</code>
            </p>
            @endif
        </div>
        <div class="flex items-center gap-3 self-start md:self-auto flex-wrap">
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-outline-variant rounded-xl text-sm">
                <div class="w-2 h-2 rounded-full bg-slate-300 animate-pulse" id="conn-dot"></div>
                <span class="font-medium text-slate-500 text-xs" id="conn-label">Connecting…</span>
            </div>
        </div>
    </div>

    {{-- ── KPI Tiles (DB range stats) ────────────────────────────── --}}
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-outline-variant rounded-xl p-5">
            <span class="font-label-caps text-label-caps text-on-surface-variant block mb-2">AVG HUMIDITY</span>
            <p class="font-data-num text-3xl text-on-background leading-none">{{ $rangeStats['avg_humidity'] ?? '--' }}</p>
            <p class="text-xs text-slate-400 mt-1">% — selected range</p>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-5">
            <span class="font-label-caps text-label-caps text-on-surface-variant block mb-2">PEAK HUMIDITY</span>
            <p class="font-data-num text-3xl text-on-background leading-none">{{ isset($rangeStats['max_humidity']) ? number_format($rangeStats['max_humidity'], 1) : '--' }}</p>
            <p class="text-xs text-slate-400 mt-1">% — selected range</p>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-5">
            <span class="font-label-caps text-label-caps text-on-surface-variant block mb-2">AVG TEMP</span>
            <p class="font-data-num text-3xl text-on-background leading-none">{{ $rangeStats['avg_temp'] ?? '--' }}</p>
            <p class="text-xs text-slate-400 mt-1">°C — selected range</p>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-5">
            <span class="font-label-caps text-label-caps text-on-surface-variant block mb-2">READINGS</span>
            <p class="font-data-num text-3xl text-on-background leading-none">{{ isset($rangeStats['count']) ? number_format($rangeStats['count']) : number_format($dbStats['total_readings'] ?? 0) }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ isset($rangeStats['count']) ? 'in selected range' : 'total in database' }}</p>
        </div>
    </section>

    {{-- ── Main Chart + Summary ────────────────────────────────── --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-grid-gutter">

        {{-- DB History Chart --}}
        <div class="lg:col-span-8 bg-white border border-outline-variant rounded-xl p-md flex flex-col min-h-[420px]">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
                <div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Sensor History</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Hourly averages from database · Warn: {{ $warnThresh }}% / Crit: {{ $critThresh }}%
                    </p>
                </div>
                {{-- Date range filter --}}
                <form method="GET" action="{{ route('analytics') }}" class="flex items-center gap-2 flex-wrap">
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
                           max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}"
                           class="px-2.5 py-1.5 border border-outline-variant rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <span class="text-xs text-slate-400">to</span>
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
                           max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}"
                           class="px-2.5 py-1.5 border border-outline-variant rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <button type="submit" class="px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-semibold hover:opacity-90 transition-colors">
                        Apply
                    </button>
                </form>
            </div>
            <div class="flex-1 relative min-h-[280px]">
                @if($historyData->isEmpty())
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                        <span class="material-symbols-outlined text-4xl mb-2">query_stats</span>
                        <p class="text-sm font-medium">No readings in this date range</p>
                        <p class="text-xs mt-1">Make sure the scheduler is running and try a different range</p>
                    </div>
                @endif
                <canvas id="analytics-chart"></canvas>
            </div>
            <div class="flex items-center gap-6 mt-4 pt-4 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-silica-500"></span>
                    <span class="text-xs font-semibold text-on-surface-variant uppercase">Humidity (%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                    <span class="text-xs font-semibold text-on-surface-variant uppercase">Temperature (°C)</span>
                </div>
                <span class="ml-auto text-xs text-slate-400">{{ $historyData->count() }} hourly data points</span>
            </div>
        </div>

        {{-- Live Summary Panel --}}
        <div class="lg:col-span-4 bg-tertiary-container text-on-tertiary-container rounded-xl p-md border border-tertiary shadow-lg flex flex-col justify-between overflow-hidden relative">
            <div class="relative z-10 space-y-4">
                <div>
                    <span class="inline-block px-2 py-1 bg-white/20 rounded-lg text-[10px] font-bold uppercase tracking-widest mb-3">Current Snapshot</span>
                    <h3 class="font-headline-md text-headline-md mb-1">Live Readings</h3>
                    <p class="font-body-sm text-body-sm text-white/60">Updated every Firebase push event</p>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">water_drop</span>
                            <span class="text-sm font-medium">Humidity</span>
                        </div>
                        <span class="font-data-num text-2xl" id="snap-hum">--<span class="text-sm">%</span></span>
                    </div>
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">thermostat</span>
                            <span class="text-sm font-medium">Temperature</span>
                        </div>
                        <span class="font-data-num text-2xl" id="snap-temp">--<span class="text-sm">°C</span></span>
                    </div>
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">sensors</span>
                            <span class="text-sm font-medium">Status</span>
                        </div>
                        <span class="font-data-num text-lg font-bold" id="snap-status">--</span>
                    </div>
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">schedule</span>
                            <span class="text-sm font-medium">Last Read</span>
                        </div>
                        <span class="text-sm font-semibold" id="snap-time">--</span>
                    </div>
                </div>
            </div>
            <div class="absolute -right-12 -bottom-8 opacity-10 transform -rotate-12 pointer-events-none">
                <span class="material-symbols-outlined text-[160px]" style="font-variation-settings:'wght' 200">query_stats</span>
            </div>
        </div>
    </section>

    {{-- ── Condition Report Generator ───────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-md">
        <div class="flex items-start gap-4 mb-6">
            <div class="p-3 bg-primary/10 rounded-xl flex-shrink-0">
                <span class="material-symbols-outlined text-primary">summarize</span>
            </div>
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Condition Report</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Download a CSV of real sensor history and alert log for any date range.
                    The file includes readings, alerts, and summary statistics.
                </p>
            </div>
        </div>

        @if($primary)
        <form id="report-form" class="flex flex-wrap gap-4 items-end" onsubmit="return false;">
            <input type="hidden" id="report-device-id" value="{{ $primary->id }}">

            {{-- From date --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">From</label>
                <input type="date" id="report-from"
                       value="{{ now(config('app.display_timezone'))->subDays(30)->format('Y-m-d') }}"
                       max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}"
                       class="px-3 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
            </div>

            {{-- To date --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">To</label>
                <input type="date" id="report-to"
                       value="{{ now(config('app.display_timezone'))->format('Y-m-d') }}"
                       max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}"
                       class="px-3 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
            </div>

            <button type="button" onclick="downloadCsvReport()"
                    class="flex items-center gap-2 px-6 py-2.5 bg-slate-100 text-slate-700 border border-outline-variant rounded-xl text-sm font-bold hover:bg-slate-200 transition-colors">
                <span class="material-symbols-outlined text-sm">download</span>
                Download CSV
            </button>

            {{-- Grouped together: the checkbox only affects the email action, not the CSV download above --}}
            <div class="flex flex-col gap-1.5">
                <button type="button" onclick="emailReport()" id="email-report-btn"
                        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-bold hover:opacity-90 transition-colors shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-sm">mail</span>
                    Email Me This Report
                </button>
                <label class="flex items-center gap-2 text-xs text-slate-500 cursor-pointer select-none">
                    <input type="checkbox" id="report-include-csv" class="rounded border-outline-variant text-primary focus:ring-primary/30">
                    Include CSV attachment
                </label>
            </div>
        </form>

        <div id="report-result" class="hidden mt-4 p-3 rounded-xl border text-sm"></div>

        {{-- DB Stats row --}}
        @if(!empty($dbStats))
        <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant mb-1">STORED READINGS</p>
                <p class="font-data-num text-2xl text-on-background">{{ number_format($dbStats['total_readings']) }}</p>
            </div>
            <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant mb-1">TOTAL ALERTS</p>
                <p class="font-data-num text-2xl text-on-background">{{ number_format($dbStats['total_alerts']) }}</p>
            </div>
            <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant mb-1">FIRST READING</p>
                <p class="text-sm font-semibold text-on-surface">
                    {{ $dbStats['earliest'] ? \Carbon\Carbon::parse($dbStats['earliest'], 'UTC')->timezone(config('app.display_timezone'))->format('d M Y, H:i') : '—' }}
                </p>
            </div>
            <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant mb-1">LATEST READING</p>
                <p class="text-sm font-semibold text-on-surface">
                    {{ $dbStats['latest'] ? \Carbon\Carbon::parse($dbStats['latest'], 'UTC')->timezone(config('app.display_timezone'))->format('d M Y, H:i') : '—' }}
                </p>
            </div>
        </div>
        @endif

        @else
        {{-- No device yet --}}
        <div class="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-sm">
            <span class="material-symbols-outlined text-base">info</span>
            No active device found. <a href="{{ route('device') }}" class="font-bold underline ml-1">Add a device</a> to generate reports.
        </div>
        @endif
    </section>

</div>
@endsection

@section('scripts')
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
const CSRF = '{{ csrf_token() }}';

// ── DB history data (server-rendered) ────────────────────────
@php
    $chartLabels = $historyData->map(fn($r) => \Carbon\Carbon::parse($r->hour)->format('d M H:i'));
    $chartHum    = $historyData->map(fn($r) => (float) $r->avg_humidity);
    $chartTemp   = $historyData->map(fn($r) => (float) $r->avg_temperature);
@endphp
const dbLabels = @json($chartLabels);
const dbHum    = @json($chartHum);
const dbTemp   = @json($chartTemp);

// ── History Chart ─────────────────────────────────────────────
const ctx = document.getElementById('analytics-chart').getContext('2d');
const analyticsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: dbLabels,
        datasets: [
            { label: 'Humidity (%)',     data: dbHum,  borderColor: '#c2410c', backgroundColor: 'rgba(255,122,41,0.08)', fill: true, tension: 0.4, pointRadius: dbLabels.length > 72 ? 0 : 3, borderWidth: 2.5, yAxisID: 'yHum' },
            { label: 'Temperature (°C)', data: dbTemp, borderColor: '#8a958e', backgroundColor: 'rgba(138,149,142,0.06)', fill: true, tension: 0.4, pointRadius: dbLabels.length > 72 ? 0 : 3, borderWidth: 2.5, yAxisID: 'yTemp' },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: c => c.datasetIndex === 0 ? ` Humidity: ${c.parsed.y.toFixed(1)}%` : ` Temp: ${c.parsed.y.toFixed(1)}°C` } }
        },
        scales: {
            yHum:  { position: 'left',  min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => v + '%', font: { size: 11 } }, title: { display: true, text: 'Humidity (%)', font: { size: 11 } } },
            yTemp: { position: 'right', min: 0, max: 60,  grid: { display: false }, ticks: { callback: v => v + '°', font: { size: 11 } }, title: { display: true, text: 'Temperature (°C)', font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } }
        },
        animation: { duration: 300 }
    }
});

// ── Firebase (live snapshot panel) ─────────────────────────────
const firebaseConfig = {
    apiKey:      "{{ config('firebase.api_key') }}",
    databaseURL: "{{ config('firebase.database_url') }}",
};
if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
const db = firebase.database();

const connDot     = document.getElementById('conn-dot');
const connLabel   = document.getElementById('conn-label');

db.ref('.info/connected').on('value', snap => {
    const live = snap.val() === true;
    connDot.className     = `w-2 h-2 rounded-full ${live ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400 animate-pulse'}`;
    connLabel.textContent = live ? 'Live' : 'Reconnecting…';
    connLabel.className   = `font-medium text-xs ${live ? 'text-emerald-600' : 'text-amber-500'}`;
});

db.ref("{{ $firebasePath }}").on("value", snapshot => {
    const data = snapshot.val();
    if (!data) return;

    const time = new Date().toLocaleTimeString();
    const hum  = parseFloat(data.humidity    ?? 0);
    const temp = parseFloat(data.temperature ?? 0);
    const stat = data.status || 'Unknown';

    document.getElementById('snap-hum').innerHTML      = `${hum.toFixed(1)}<span class="text-sm">%</span>`;
    document.getElementById('snap-temp').innerHTML     = `${temp.toFixed(1)}<span class="text-sm">°C</span>`;
    document.getElementById('snap-status').textContent = stat;
    document.getElementById('snap-time').textContent   = time;
});

// ── Condition Report (CSV download / emailed report) ──────────
function reportParams() {
    return {
        device_id: document.getElementById('report-device-id')?.value,
        from: document.getElementById('report-from')?.value,
        to: document.getElementById('report-to')?.value,
    };
}

window.downloadCsvReport = function() {
    const p = reportParams();
    if (!p.from || !p.to) { alert('Pick a from/to date first.'); return; }
    const qs = new URLSearchParams(p).toString();
    window.location.href = `{{ route('report.generate') }}?${qs}`;
};

window.emailReport = async function() {
    const p = reportParams();
    if (!p.from || !p.to) { alert('Pick a from/to date first.'); return; }

    const btn = document.getElementById('email-report-btn');
    const includeCsv = document.getElementById('report-include-csv')?.checked ?? false;
    const resultEl = document.getElementById('report-result');

    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">autorenew</span> Sending…';
    resultEl.classList.add('hidden');

    try {
        const resp = await fetch(`{{ route('report.email') }}`, {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN':  CSRF,
                'Accept':        'application/json',
                'Content-Type':  'application/json',
            },
            body: JSON.stringify({ ...p, include_csv: includeCsv }),
        });
        const json = await resp.json();

        resultEl.classList.remove('hidden');
        if (resp.ok && json.success) {
            resultEl.className = 'mt-4 p-3 rounded-xl border text-sm bg-emerald-50 border-emerald-200 text-emerald-700';
            resultEl.textContent = `Report queued — it'll land in your inbox shortly${includeCsv ? ' with the CSV attached' : ''}.`;
        } else {
            resultEl.className = 'mt-4 p-3 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700';
            resultEl.textContent = json.message || 'Something went wrong requesting the report.';
        }
    } catch {
        resultEl.classList.remove('hidden');
        resultEl.className = 'mt-4 p-3 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700';
        resultEl.textContent = 'Something went wrong requesting the report — please try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
};
</script>
@endsection
