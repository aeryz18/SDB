@extends('layouts.app')

@section('title', 'Equipment Inventory | DryBox AI')

@section('content')
<div class="max-w-7xl mx-auto space-y-md">

    {{-- ── Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">Equipment Inventory</h1>
            <p class="font-body-base text-on-surface-variant mt-1">
                Live status of your dry storage unit —
                <span class="font-semibold text-on-surface">{{ $device ? 1 : 0 }} unit{{ $device ? '' : 's' }} registered</span>
            </p>
        </div>
        <div class="flex items-center gap-3 self-start sm:self-auto">
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-outline-variant rounded-xl text-sm">
                <div class="w-2 h-2 rounded-full bg-slate-300 animate-pulse" id="conn-dot"></div>
                <span class="font-medium text-slate-500" id="conn-label">Connecting…</span>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm font-medium" id="flash-msg">
        <span class="material-symbols-outlined text-base">check_circle</span>
        {{ session('success') }}
        <button onclick="document.getElementById('flash-msg').remove()" class="ml-auto text-emerald-500 hover:text-emerald-700">
            <span class="material-symbols-outlined text-base">close</span>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium">
        <span class="material-symbols-outlined text-base">error</span>
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Summary Stats Bar ──────────────────────────────────────── --}}
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">ACTIVE UNITS</span>
            <span class="font-data-num text-3xl text-on-background leading-none">{{ $device ? 1 : 0 }}</span>
            <span class="text-xs text-slate-400">registered devices</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">TEMPERATURE</span>
            <span class="font-data-num text-3xl text-on-background leading-none" id="stat-temp">--</span>
            <span class="text-xs text-slate-400">°C — highest</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">HUMIDITY</span>
            <span class="font-data-num text-3xl text-on-background leading-none" id="stat-hum">--</span>
            <span class="text-xs text-slate-400">% — worst case</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">SYSTEM STATUS</span>
            <span class="font-headline-md text-xl font-bold leading-none mt-1 text-slate-400" id="stat-status">--</span>
            <span class="text-xs text-slate-400" id="stat-time">no readings yet</span>
        </div>
    </section>

    {{-- ── Equipment Cards Grid ─────────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

        @if ($device)
        @php
            $silicaReplaced = $device->settings?->silica_last_replaced_at;
            $interval       = $device->settings?->silica_interval_days ?? 90;
            $daysSince      = $silicaReplaced ? now()->diffInDays($silicaReplaced) : $interval + 1;
            $daysLeft       = max(0, $interval - (int) $daysSince);
            $silicaDue      = $daysLeft <= 0;
            $silicaWarning  = !$silicaDue && $daysLeft <= 14;
            $protected      = $device->settings?->protection_mode ?? false;
        @endphp
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden hover:shadow-lg transition-all group" id="card-{{ $device->id }}">
            {{-- Colour-coded top strip --}}
            <div class="h-1 bg-slate-200 transition-colors" id="topbar-{{ $device->id }}"></div>

            <div class="p-6">
                {{-- Status badge + icon --}}
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 flex items-center gap-1.5" id="badge-{{ $device->id }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse"></span>
                        Connecting…
                    </span>
                    <span class="material-symbols-outlined text-slate-300 group-hover:text-primary transition-colors">sensors</span>
                </div>

                {{-- Name + Firebase path --}}
                <h3 class="font-display-lg text-xl font-bold text-primary mb-0.5">{{ $device->name }}</h3>
                <p class="text-sm text-slate-500 mb-4">
                    @if($device->location)<span class="text-slate-400">{{ $device->location }}</span> — @endif
                    <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs text-slate-600">{{ $device->firebase_path }}/</code>
                </p>

                {{-- Live readings (JS-filled) --}}
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-orange-400" style="font-size:14px">thermostat</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temp</span>
                        </div>
                        <p class="font-data-num text-2xl text-on-background leading-none" id="temp-{{ $device->id }}">--<span class="text-sm text-slate-400">°C</span></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-blue-400" style="font-size:14px">water_drop</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
                        </div>
                        <p class="font-data-num text-2xl text-on-background leading-none" id="hum-{{ $device->id }}">--<span class="text-sm text-slate-400">%</span></p>
                    </div>
                </div>

                {{-- Humidity bar --}}
                <div class="mb-5">
                    <div class="flex justify-between text-xs text-slate-400 mb-1.5">
                        <span>Humidity level</span>
                        <span id="hum-pct-{{ $device->id }}">--%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full transition-all duration-700" id="bar-{{ $device->id }}" style="width:0%"></div>
                    </div>
                </div>

                {{-- Silica + Protection section --}}
                <div class="border-t border-slate-100 pt-4 space-y-3">

                    {{-- Silica gel status --}}
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined {{ $silicaDue ? 'text-red-500' : ($silicaWarning ? 'text-amber-500' : 'text-emerald-500') }}" style="font-size:18px">science</span>
                            <div>
                                <p class="text-xs font-semibold text-slate-600">Silica Gel</p>
                                <p class="text-[11px] {{ $silicaDue ? 'text-red-500 font-semibold' : ($silicaWarning ? 'text-amber-500' : 'text-slate-400') }}" id="silica-label-{{ $device->id }}">
                                    @if($silicaDue)
                                        Replacement overdue
                                    @elseif($silicaWarning)
                                        {{ $daysLeft }}d remaining — replace soon
                                    @elseif($silicaReplaced)
                                        {{ $daysLeft }}d remaining
                                    @else
                                        Never replaced
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button
                            onclick="markSilicaReplaced({{ $device->id }}, {{ $interval }})"
                            id="silica-btn-{{ $device->id }}"
                            class="text-xs px-2.5 py-1 rounded-lg {{ $silicaDue ? 'bg-red-50 text-red-600 border border-red-200 hover:bg-red-100' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100' }} transition-colors whitespace-nowrap">
                            Mark Replaced
                        </button>
                    </div>

                    {{-- Protection mode toggle --}}
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined {{ $protected ? 'text-purple-500' : 'text-slate-300' }}" style="font-size:18px">security</span>
                            <div>
                                <p class="text-xs font-semibold text-slate-600">Protection Mode</p>
                                <p class="text-[11px] text-slate-400" id="protection-label-{{ $device->id }}">{{ $protected ? 'Armed — tamper alerts on' : 'Disarmed' }}</p>
                            </div>
                        </div>
                        <button
                            onclick="toggleProtection({{ $device->id }}, this)"
                            id="protection-btn-{{ $device->id }}"
                            data-armed="{{ $protected ? 'true' : 'false' }}"
                            class="relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors {{ $protected ? 'bg-purple-500' : 'bg-slate-200' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $protected ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                        </button>
                    </div>
                </div>

                {{-- Last seen footer --}}
                <div class="flex justify-between items-center text-xs text-slate-400 mt-4 pt-4 border-t border-slate-100">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:13px">schedule</span>
                        <span id="time-{{ $device->id }}">--</span>
                    </span>
                    <span id="status-text-{{ $device->id }}" class="font-semibold text-slate-400">Loading…</span>
                </div>
            </div>

            {{-- View Details / Delete row --}}
            <div class="flex border-t border-slate-100">
                <button
                    onclick="openModal({{ $device->id }})"
                    class="flex-1 py-3 text-primary font-bold text-sm hover:bg-primary/5 transition-colors border-r border-slate-100">
                    View Details
                </button>
                <button
                    onclick="confirmDelete({{ $device->id }}, '{{ addslashes($device->name) }}')"
                    class="px-4 py-3 text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors text-sm">
                    <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                </button>
            </div>
        </div>
        @else
        <div class="col-span-full py-16 flex flex-col items-center justify-center text-slate-400">
            <span class="material-symbols-outlined text-5xl mb-3 text-slate-300">sensors_off</span>
            <p class="font-semibold text-slate-500 mb-1">No unit registered yet</p>
            <p class="text-sm">Add your DryBox unit below to start monitoring.</p>
        </div>
        @endif

        {{-- Add New Unit tile — only until the one device is registered --}}
        @unless($device)
        <div class="border-2 border-dashed border-outline-variant rounded-xl flex flex-col items-center justify-center p-8 text-slate-400 hover:bg-slate-50 hover:border-primary/40 transition-colors cursor-pointer group"
             onclick="openAddModal()">
            <div class="w-14 h-14 rounded-full bg-slate-100 group-hover:bg-primary/10 flex items-center justify-center mb-3 group-hover:scale-110 transition-all">
                <span class="material-symbols-outlined text-3xl group-hover:text-primary transition-colors">add</span>
            </div>
            <span class="font-semibold text-sm group-hover:text-primary transition-colors">Add New Unit</span>
            <p class="text-xs mt-1 text-center">Register your IoT sensor node</p>
        </div>
        @endunless
    </section>

    {{-- ── Detail Modal ──────────────────────────────────────────── --}}
    <div id="detail-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden z-10">
            <div class="h-1 w-full" id="modal-top-bar"></div>
            <div class="p-8">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="font-display-lg text-2xl font-bold text-primary" id="modal-title">--</h2>
                        <p class="text-sm text-slate-500 mt-1" id="modal-subtitle">--</p>
                    </div>
                    <button onclick="closeModal()" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                {{-- Sensor readings --}}
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-orange-500 text-base">thermostat</span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Temperature</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background" id="modal-temp">--<span class="text-lg text-slate-400">°C</span></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-blue-500 text-base">water_drop</span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background" id="modal-hum">--<span class="text-lg text-slate-400">%</span></p>
                    </div>
                </div>

                {{-- Info rows --}}
                <div class="space-y-0 mb-6">
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Sensor Status</span>
                        <span class="font-semibold text-sm" id="modal-status">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Firebase Path</span>
                        <code class="font-mono text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded" id="modal-path">--</code>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Last Reading</span>
                        <span class="font-semibold text-sm text-slate-700" id="modal-time">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Humidity Level</span>
                        <div class="w-36">
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-700" id="modal-bar" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Silica Gel</span>
                        <span class="text-sm font-medium" id="modal-silica">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-slate-500">Protection Mode</span>
                        <span class="text-sm font-medium" id="modal-protection">--</span>
                    </div>
                </div>

                <button onclick="closeModal()" class="w-full py-3 bg-primary text-white rounded-xl font-semibold hover:opacity-90 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ── Add New Unit Modal ─────────────────────────────────── --}}
    <div id="add-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeAddModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="font-display-lg text-xl font-bold text-primary">Add New Unit</h2>
                    <button onclick="closeAddModal()" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                <form method="POST" action="{{ route('devices.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Unit Name <span class="text-red-400">*</span></label>
                        <input
                            name="name"
                            type="text"
                            placeholder="e.g. DryBox Unit 2"
                            required
                            value="{{ old('name') }}"
                            class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Location <span class="text-slate-400 font-normal">(optional)</span></label>
                        <input
                            name="location"
                            type="text"
                            placeholder="e.g. Warehouse B, Shelf 3"
                            value="{{ old('location') }}"
                            class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Firebase Path <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-mono">rtdb:/</span>
                            <input
                                name="firebase_path"
                                type="text"
                                placeholder="drybox2"
                                required
                                value="{{ old('firebase_path') }}"
                                class="w-full pl-16 pr-4 py-2.5 border border-outline-variant rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                        </div>
                        <p class="text-xs text-slate-400 mt-1">The root node name in your Firebase Realtime Database (e.g. <code class="bg-slate-100 px-1 rounded">drybox2</code>).</p>
                        @error('firebase_path')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeAddModal()" class="flex-1 py-2.5 border border-outline-variant text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-colors">
                            Add Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete form (hidden, submitted by confirmDelete JS) --}}
    <form id="delete-form" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
    </form>

</div>
@endsection

@php
$deviceJson = $device ? [[
    'id'            => $device->id,
    'name'          => $device->name,
    'firebase_path' => $device->firebase_path,
    'location'      => $device->location,
    'interval_days' => $device->settings?->silica_interval_days ?? 90,
    'protection'    => (bool) ($device->settings?->protection_mode ?? false),
]] : [];
@endphp

@section('scripts')
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>
<script>
// ── Firebase ──────────────────────────────────────────────────────
const firebaseConfig = {
    apiKey:      "{{ config('firebase.api_key') }}",
    databaseURL: "{{ config('firebase.database_url') }}",
};
if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
const db = firebase.database();

// ── Config ────────────────────────────────────────────────────────
const CSRF        = '{{ csrf_token() }}';
const CRIT_THRESH = parseInt(localStorage.getItem('critThreshold') ?? 45);
const WARN_THRESH = parseInt(localStorage.getItem('warnThreshold') ?? 35);

// Server-side device list (injected by Blade)
const serverDevices = @json($deviceJson);

// Runtime state
const deviceData     = {};
let   currentModalId = null;

// ── Status helpers ────────────────────────────────────────────────
function getStatusMeta(statusStr, humidity) {
    const s = (statusStr || '').toLowerCase();
    if (s.includes('critical') || humidity > CRIT_THRESH) {
        return { label:'CRITICAL', badge:'bg-red-100 text-red-700',      bar:'bg-red-500',     topBar:'bg-red-500',     dot:'bg-red-500',     text:'text-red-600' };
    } else if (s.includes('warning') || humidity > WARN_THRESH) {
        return { label:'WARNING',  badge:'bg-amber-100 text-amber-700',   bar:'bg-amber-500',   topBar:'bg-amber-500',   dot:'bg-amber-500',   text:'text-amber-600' };
    }
    return     { label:'SAFE',     badge:'bg-emerald-100 text-emerald-700', bar:'bg-blue-500', topBar:'bg-emerald-500', dot:'bg-emerald-500', text:'text-emerald-600' };
}

// ── Connection indicator ──────────────────────────────────────────
db.ref('.info/connected').on('value', snap => {
    const dot   = document.getElementById('conn-dot');
    const label = document.getElementById('conn-label');
    if (snap.val()) {
        dot.className   = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
        label.textContent = 'Live';
        label.className = 'font-medium text-emerald-600';
    } else {
        dot.className   = 'w-2 h-2 rounded-full bg-amber-400 animate-pulse';
        label.textContent = 'Reconnecting…';
        label.className = 'font-medium text-amber-500';
    }
});

// ── Update a single card's live readings ──────────────────────────
function updateCard(deviceId, data) {
    const hum    = parseFloat(data.humidity    ?? 0);
    const temp   = parseFloat(data.temperature ?? 0);
    const status = data.status || 'Unknown';
    const now    = new Date().toLocaleTimeString();
    const meta   = getStatusMeta(status, hum);

    const el = id => document.getElementById(`${id}-${deviceId}`);

    // Top colour strip
    const topBar = el('topbar');
    if (topBar) topBar.className = `h-1 transition-colors ${meta.topBar}`;

    // Status badge
    const badge = el('badge');
    if (badge) {
        badge.className = `text-xs font-bold px-2.5 py-1 rounded-full ${meta.badge} flex items-center gap-1.5`;
        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${meta.dot} animate-pulse"></span>${meta.label}`;
    }

    // Readings
    const tempEl = el('temp');
    if (tempEl) tempEl.innerHTML = `${temp.toFixed(1)}<span class="text-sm text-slate-400">°C</span>`;

    const humEl = el('hum');
    if (humEl) humEl.innerHTML = `${hum.toFixed(1)}<span class="text-sm text-slate-400">%</span>`;

    const humPct = el('hum-pct');
    if (humPct) humPct.textContent = `${hum.toFixed(1)}%`;

    const bar = el('bar');
    if (bar) {
        bar.style.width = `${Math.min(hum, 100)}%`;
        bar.className   = `h-full ${meta.bar} rounded-full transition-all duration-700`;
    }

    // Footer
    const timeEl = el('time');
    if (timeEl) timeEl.textContent = now;

    const statusText = el('status-text');
    if (statusText) {
        statusText.textContent = status;
        statusText.className   = `font-semibold text-xs ${meta.text}`;
    }

    // Cache for modal + stats bar
    deviceData[deviceId] = { hum, temp, status, time: now, meta };

    // Update stats bar
    updateStatsBar();

    // Update modal if this device is open
    if (currentModalId === deviceId) {
        const modal = document.getElementById('detail-modal');
        if (modal && !modal.classList.contains('hidden')) {
            fillModal(deviceId);
        }
    }
}

// ── Stats bar (worst-case across all devices) ─────────────────────
function updateStatsBar() {
    const readings = Object.values(deviceData);
    if (readings.length === 0) return;

    const worst   = readings.reduce((a, b) => a.hum >= b.hum ? a : b);
    const latest  = readings.reduce((a, b) => a.time >= b.time ? a : b);

    const statTemp   = document.getElementById('stat-temp');
    const statHum    = document.getElementById('stat-hum');
    const statStatus = document.getElementById('stat-status');
    const statTime   = document.getElementById('stat-time');

    if (statTemp)   statTemp.textContent   = worst.temp.toFixed(1);
    if (statHum)    statHum.textContent    = worst.hum.toFixed(1);
    if (statStatus) {
        statStatus.textContent = worst.meta.label;
        statStatus.className   = `font-headline-md text-xl font-bold leading-none mt-1 ${worst.meta.text}`;
    }
    if (statTime) statTime.textContent = `last reading ${latest.time}`;
}

// ── Per-device Firebase listeners ─────────────────────────────────
serverDevices.forEach(device => {
    db.ref(device.firebase_path).on('value', snapshot => {
        const data = snapshot.val();
        if (data) updateCard(device.id, data);
    });
});

// ── Detail modal ──────────────────────────────────────────────────
function openModal(deviceId) {
    currentModalId = deviceId;
    fillModal(deviceId);
    const modal = document.getElementById('detail-modal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function fillModal(deviceId) {
    const dev  = serverDevices.find(d => d.id === deviceId);
    const live = deviceData[deviceId];
    const hum  = live?.hum  ?? 0;
    const temp = live?.temp ?? 0;
    const meta = live?.meta ?? getStatusMeta('', 0);

    document.getElementById('modal-top-bar').className    = `h-1 w-full ${meta.topBar}`;
    document.getElementById('modal-title').textContent    = dev?.name ?? '--';
    document.getElementById('modal-subtitle').textContent = dev?.location
        ? `${dev.location} — ${meta.label}`
        : meta.label;
    document.getElementById('modal-temp').innerHTML    = `${temp.toFixed(1)}<span class="text-lg text-slate-400">°C</span>`;
    document.getElementById('modal-hum').innerHTML     = `${hum.toFixed(1)}<span class="text-lg text-slate-400">%</span>`;
    document.getElementById('modal-status').textContent  = live?.status ?? '--';
    document.getElementById('modal-status').className    = `font-semibold text-sm ${meta.text}`;
    document.getElementById('modal-path').textContent    = `${dev?.firebase_path ?? '--'}/`;
    document.getElementById('modal-time').textContent    = live?.time ?? '--';
    document.getElementById('modal-bar').style.width     = `${Math.min(hum, 100)}%`;
    document.getElementById('modal-bar').className       = `h-full rounded-full transition-all duration-700 ${meta.bar}`;

    // Silica + protection (static from serverDevices)
    const silicaLabel  = document.getElementById('silica-label-' + deviceId);
    const protLabel    = document.getElementById('protection-label-' + deviceId);
    document.getElementById('modal-silica').textContent     = silicaLabel?.textContent     ?? '--';
    document.getElementById('modal-protection').textContent = protLabel?.textContent        ?? '--';
    document.getElementById('modal-protection').className   = `text-sm font-medium ${dev?.protection ? 'text-purple-600' : 'text-slate-400'}`;
}

function closeModal() {
    document.getElementById('detail-modal').classList.add('hidden');
    document.body.style.overflow = '';
    currentModalId = null;
}

// ── Add Unit modal ────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('add-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddModal() {
    document.getElementById('add-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Auto-open add modal if there were validation errors
@if($errors->has('name') || $errors->has('firebase_path'))
openAddModal();
@endif

// ── Delete device ─────────────────────────────────────────────────
function confirmDelete(deviceId, deviceName) {
    if (!confirm(`Remove "${deviceName}" from your account?\n\nThis will permanently delete all stored readings and alerts for this device. This cannot be undone.`)) return;
    const form   = document.getElementById('delete-form');
    form.action  = `/devices/${deviceId}`;
    form.submit();
}

// ── Silica replacement ────────────────────────────────────────────
async function markSilicaReplaced(deviceId, intervalDays) {
    const btn   = document.getElementById(`silica-btn-${deviceId}`);
    const label = document.getElementById(`silica-label-${deviceId}`);
    if (!btn) return;

    btn.disabled    = true;
    btn.textContent = 'Saving…';

    try {
        const resp = await fetch(`/devices/${deviceId}/silica`, {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept':       'application/json',
            },
        });

        if (resp.ok) {
            const days = intervalDays ?? 90;
            if (label) {
                label.textContent = `${days}d remaining`;
                label.className   = 'text-[11px] text-slate-400';
            }
            btn.className   = 'text-xs px-2.5 py-1 rounded-lg bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100 transition-colors whitespace-nowrap';
            btn.textContent = 'Mark Replaced';

            // Update the parent science icon colour
            const icon = btn.closest('div.flex')?.querySelector('.material-symbols-outlined');
            if (icon) icon.className = icon.className.replace(/text-(red|amber)-500/, 'text-emerald-500').replace('material-symbols-outlined', 'material-symbols-outlined');
        } else {
            btn.textContent = 'Error — retry';
        }
    } catch {
        btn.textContent = 'Error — retry';
    } finally {
        btn.disabled = false;
    }
}

// ── Protection mode toggle ────────────────────────────────────────
async function toggleProtection(deviceId, btn) {
    if (!btn) return;
    btn.disabled = true;

    try {
        const resp = await fetch(`/devices/${deviceId}/protection`, {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept':       'application/json',
            },
        });

        const json  = await resp.json();
        const armed = json.protection_mode;

        btn.className    = `relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors ${armed ? 'bg-purple-500' : 'bg-slate-200'}`;
        btn.dataset.armed = armed ? 'true' : 'false';
        const knob = btn.querySelector('span');
        if (knob) knob.className = `inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${armed ? 'translate-x-4' : 'translate-x-0.5'}`;

        const label = document.getElementById(`protection-label-${deviceId}`);
        if (label) label.textContent = armed ? 'Armed — tamper alerts on' : 'Disarmed';

        const icon = document.querySelector(`#card-${deviceId} .material-symbols-outlined[style*="18px"]`);
        if (icon && icon.textContent.trim() === 'security') {
            icon.className = icon.className.replace(/text-(purple|slate)-(500|300)/, armed ? 'text-purple-500' : 'text-slate-300');
        }

        // Update serverDevices cache
        const cached = serverDevices.find(d => d.id === deviceId);
        if (cached) cached.protection = armed;

    } catch (e) {
        console.error('toggleProtection error:', e);
    } finally {
        btn.disabled = false;
    }
}

// ── ESC to close any open modal ───────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeModal();
    closeAddModal();
});
</script>
@endsection
