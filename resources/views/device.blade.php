@extends('layouts.app')

@section('title', 'Device | Incognito')

@php
$warn = $settings?->warn_humidity        ?? 35;
$crit = $settings?->crit_humidity        ?? 45;
$silicaDays = $settings?->silica_interval_days ?? 90;
$notifyList = implode(', ', $settings?->notify_emails ?? [auth()->user()->email]);
@endphp

@section('content')
<div class="max-w-6xl mx-auto pb-28 space-y-8">

    {{-- ── Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">Device</h1>
            <p class="font-body-base text-on-surface-variant mt-1">Live status and configuration for your dry storage unit.</p>
        </div>
        <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-outline-variant rounded-xl text-sm self-start sm:self-auto">
            <div class="w-2 h-2 rounded-full bg-slate-300 animate-pulse" id="conn-dot"></div>
            <span class="font-medium text-slate-500" id="conn-label">Connecting…</span>
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

    {{-- ── Live Status ───────────────────────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl overflow-hidden">
        <div class="h-1.5 bg-slate-200 transition-colors" id="topbar"></div>

        @if($device)
        @php
            $silicaReplaced = $silica['replaced'];
            $daysLeft       = max(0, $silica['days_left']); // clamp only for display; canonical signed value is $silica['days_left']
            $silicaDue      = $silica['due'];
            $silicaWarning  = $silica['warning'];
            $protected      = $device->settings?->protection_mode ?? false;
        @endphp
        <div class="p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
                {{-- Identity --}}
                <div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 inline-flex items-center gap-1.5 mb-2" id="badge">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse"></span>
                        Connecting…
                    </span>
                    <h2 class="font-display-lg text-2xl font-bold text-primary">{{ $device->name }}</h2>
                    <p class="text-sm text-slate-500 mt-0.5">
                        @if($device->location)<span class="text-slate-400">{{ $device->location }}</span> — @endif
                        <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs text-slate-600">{{ $device->firebase_path }}/</code>
                    </p>
                    <p class="flex items-center gap-1 text-xs text-slate-400 mt-3">
                        <span class="material-symbols-outlined" style="font-size:13px">schedule</span>
                        Last reading: <span id="time">--</span>
                    </p>
                </div>

                {{-- Readings --}}
                <div class="grid grid-cols-2 gap-4 w-full md:w-72 flex-shrink-0">
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-orange-400" style="font-size:16px">thermostat</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temperature</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background leading-none" id="stat-temp">--<span class="text-base text-slate-400">°C</span></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-silica-400" style="font-size:16px">water_drop</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background leading-none" id="stat-hum">--<span class="text-base text-slate-400">%</span></p>
                    </div>
                </div>
            </div>

            {{-- Humidity bar --}}
            <div class="mt-6">
                <div class="flex justify-between text-xs text-slate-400 mb-1.5">
                    <span>Humidity level</span>
                    <span id="hum-pct">--%</span>
                </div>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-silica-500 rounded-full transition-all duration-700" id="bar" style="width:0%"></div>
                </div>
            </div>

            {{-- Silica + Protection --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-slate-100">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined {{ $silicaDue ? 'text-red-500' : ($silicaWarning ? 'text-amber-500' : 'text-emerald-500') }}" style="font-size:20px">science</span>
                        <div>
                            <p class="text-sm font-semibold text-slate-600">Silica Gel</p>
                            <p class="text-xs {{ $silicaDue ? 'text-red-500 font-semibold' : ($silicaWarning ? 'text-amber-500' : 'text-slate-400') }}">
                                @if(!$silicaReplaced)
                                    Never replaced
                                @elseif($silicaDue)
                                    Replacement overdue
                                @elseif($silicaWarning)
                                    {{ $daysLeft }}d remaining — replace soon
                                @else
                                    {{ $daysLeft }}d remaining
                                @endif
                                @if($silicaAvgLifespan !== null)
                                    · avg. {{ $silicaAvgLifespan }}d lifespan
                                @endif
                            </p>
                        </div>
                    </div>
                    <a
                        href="{{ route('silica.log') }}"
                        class="text-xs px-3 py-1.5 rounded-lg {{ $silicaDue ? 'bg-red-50 text-red-600 border border-red-200 hover:bg-red-100' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100' }} transition-colors whitespace-nowrap">
                        Replace
                    </a>
                </div>

                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined {{ $protected ? 'text-purple-500' : 'text-slate-300' }}" style="font-size:20px" id="protection-icon">security</span>
                        <div>
                            <p class="text-sm font-semibold text-slate-600">Protection Mode</p>
                            <p class="text-xs text-slate-400" id="protection-label">{{ $protected ? 'Armed — tamper alerts on' : 'Disarmed' }}</p>
                        </div>
                    </div>
                    <button
                        onclick="toggleProtection({{ $device->id }}, this)"
                        id="protection-btn"
                        data-armed="{{ $protected ? 'true' : 'false' }}"
                        class="relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors {{ $protected ? 'bg-purple-500' : 'bg-slate-200' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $protected ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                    </button>
                </div>
            </div>
        </div>
        @else
        <div class="py-16 px-6 flex flex-col items-center justify-center text-slate-400">
            <span class="material-symbols-outlined text-5xl mb-3 text-slate-300">sensors_off</span>
            <p class="font-semibold text-slate-500 mb-1">No unit registered yet</p>
            <p class="text-sm mb-6 text-center">Add your dry box unit to start monitoring.</p>
            <button onclick="openAddModal()" class="px-6 py-3 bg-primary text-white rounded-xl font-semibold text-sm hover:opacity-90 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px">add</span>
                Add New Unit
            </button>
        </div>
        @endif
    </section>

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
                            placeholder="e.g. Dry Box Unit 2"
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

    {{-- ── Configuration ──────────────────────────────────────── --}}
    <div class="pt-2">
        <h2 class="font-display-lg text-2xl text-primary mb-1">Configuration</h2>
        <p class="font-body-base text-on-surface-variant">Manage thresholds, Firebase connectivity, and your account.</p>
    </div>

    {{-- Settings form wraps threshold + device settings sections --}}
    <form id="settings-form" method="POST" action="{{ route('device.save') }}">
    @csrf

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">

        {{-- ── Left Column ────────────────────────────────────── --}}
        <div class="md:col-span-8 space-y-8">

            {{-- Humidity Alerts --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-silica-500">water_drop</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Humidity Alerts</h2>
                    @if(!$device)
                    <span class="ml-auto text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full font-semibold">No device — add one first</span>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <div class="space-y-3">
                        <label for="crit-slider" class="block font-label-caps text-label-caps text-on-surface-variant">CRITICAL HUMIDITY THRESHOLD</label>
                        <div class="flex items-center gap-4">
                            <input id="crit-slider" name="crit_humidity"
                                   class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-red-500"
                                   type="range" min="20" max="80" value="{{ $crit }}"
                                   {{ !$device ? 'disabled' : '' }}>
                            <span id="crit-val" class="font-data-num text-headline-md text-red-500 min-w-[56px]">{{ $crit }}%</span>
                        </div>
                        <p class="text-body-sm text-outline">Triggers Critical alert when humidity exceeds this value.</p>
                    </div>
                    <div class="space-y-3">
                        <label for="warn-slider" class="block font-label-caps text-label-caps text-on-surface-variant">WARNING HUMIDITY THRESHOLD</label>
                        <div class="flex items-center gap-4">
                            <input id="warn-slider" name="warn_humidity"
                                   class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-amber-500"
                                   type="range" min="10" max="60" value="{{ $warn }}"
                                   {{ !$device ? 'disabled' : '' }}>
                            <span id="warn-val" class="font-data-num text-headline-md text-amber-500 min-w-[56px]">{{ $warn }}%</span>
                        </div>
                        <p class="text-body-sm text-outline">Triggers Warning alert when humidity exceeds this value.</p>
                    </div>
                </div>

                {{-- Live preview band --}}
                <div class="mt-8 p-4 bg-slate-50 rounded-xl border border-outline-variant">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Threshold Preview</p>
                    <div class="relative w-full h-6 bg-gradient-to-r from-emerald-200 via-amber-200 to-red-200 rounded-full overflow-hidden">
                        <div id="warn-marker" class="absolute top-0 h-full w-0.5 bg-amber-600" style="left:{{ $warn }}%">
                            <span class="absolute -top-5 -translate-x-1/2 text-[10px] font-bold text-amber-600" id="warn-label">{{ $warn }}%</span>
                        </div>
                        <div id="crit-marker" class="absolute top-0 h-full w-0.5 bg-red-600" style="left:{{ $crit }}%">
                            <span class="absolute -top-5 -translate-x-1/2 text-[10px] font-bold text-red-600" id="crit-label">{{ $crit }}%</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1.5">
                        <span>0%</span><span>50%</span><span>100%</span>
                    </div>
                </div>
            </section>

            {{-- Temperature Alerts --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-slate-500">device_thermostat</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Temperature Alerts</h2>
                </div>
                <div>
                    <label for="temp-min" class="block font-label-caps text-label-caps text-on-surface-variant mb-2">ACCEPTABLE RANGE (°C)</label>
                    <div class="flex items-center gap-2 max-w-sm">
                        <input id="temp-min" name="temp_min" type="number" step="0.1" min="-10" max="60"
                               value="{{ $settings?->temp_min }}"
                               placeholder="Min"
                               {{ !$device ? 'disabled' : '' }}
                               class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors disabled:bg-slate-50 disabled:text-slate-400">
                        <span class="text-slate-400">–</span>
                        <input id="temp-max" name="temp_max" type="number" step="0.1" min="-10" max="60"
                               value="{{ $settings?->temp_max }}"
                               placeholder="Max"
                               {{ !$device ? 'disabled' : '' }}
                               class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors disabled:bg-slate-50 disabled:text-slate-400">
                    </div>
                    <p class="text-body-sm text-outline mt-2">Optional — leave a side blank to skip that limit. An alert fires when temperature goes outside this range.</p>
                </div>
            </section>

            {{-- Notifications & Cooldown --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary">tune</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Notifications & Cooldown</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    {{-- Notify emails --}}
                    <div>
                        <label for="notify-emails" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base text-slate-400">email</span>
                                Alert Notification Emails
                            </span>
                        </label>
                        <input id="notify-emails" name="notify_emails" type="text"
                               value="{{ $notifyList }}"
                               placeholder="email1@example.com, email2@example.com"
                               {{ !$device ? 'disabled' : '' }}
                               class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors disabled:bg-slate-50 disabled:text-slate-400">
                        <p class="text-xs text-slate-400 mt-1">Comma-separated. Only valid email addresses are saved. Uses Gmail connected via Google login.</p>
                    </div>

                    {{-- Alert cooldown --}}
                    <div>
                        <label for="alert-cooldown" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base text-slate-400">hourglass_empty</span>
                                Alert Cooldown (minutes)
                            </span>
                        </label>
                        <input id="alert-cooldown" name="alert_cooldown_minutes" type="number"
                               min="1" max="1440" value="{{ $settings?->alert_cooldown_minutes ?? 30 }}"
                               {{ !$device ? 'disabled' : '' }}
                               class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors disabled:bg-slate-50 disabled:text-slate-400">
                        <p class="text-xs text-slate-400 mt-1">Minimum time between repeat emails for the same alert type (shared by humidity, temperature, and tamper alerts). Lower this temporarily for live demos.</p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 mt-6 pt-6 border-t border-slate-100 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-slate-300" style="font-size:14px">science</span>
                    Silica gel replacement interval and schedule are managed on the <a href="{{ route('silica.log') }}" class="text-primary font-semibold hover:underline">Silica Log</a> page.
                </p>
            </section>

            {{-- Firebase Connection --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-primary/5 rounded-full -mr-20 -mt-20 pointer-events-none"></div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary">cloud</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Firebase Connection</h2>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block font-label-caps text-label-caps text-on-surface-variant mb-2">DATABASE URL</label>
                        <div class="flex items-center gap-2 px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl font-mono text-sm text-slate-600 break-all">
                            <span class="material-symbols-outlined text-slate-400 flex-shrink-0" style="font-size:16px">link</span>
                            {{ config('firebase.database_url') }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-label-caps text-label-caps text-on-surface-variant mb-2">API KEY</label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input id="api-key-field" type="password"
                                       value="{{ config('firebase.api_key') }}"
                                       readonly
                                       class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl font-mono text-sm text-slate-700 pr-10">
                                <button type="button" onclick="toggleKey()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined" id="key-eye-icon" style="font-size:18px">visibility</span>
                                </button>
                            </div>
                            <button type="button" onclick="testConnection()" id="test-btn"
                                    class="px-5 py-3 bg-primary text-white rounded-xl font-semibold text-sm hover:opacity-90 transition flex items-center gap-2 whitespace-nowrap">
                                <span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Connection
                            </button>
                        </div>
                    </div>

                    <div id="conn-result" class="hidden p-4 rounded-xl border flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 flex-shrink-0" id="conn-icon">check_circle</span>
                        <div>
                            <p class="font-semibold text-sm" id="conn-title">--</p>
                            <p class="text-xs mt-0.5" id="conn-desc">--</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-surface-container-low rounded-xl border border-outline-variant">
                        <div class="w-3 h-3 rounded-full bg-slate-300 animate-pulse" id="fb-dot"></div>
                        <div>
                            <p class="text-sm font-semibold text-on-surface" id="fb-status-text">Checking…</p>
                            <p class="text-xs text-on-surface-variant">
                                Firebase Realtime Database
                                @if($device) — <code class="bg-slate-100 px-1 rounded">{{ $device->firebase_path }}/</code> @endif
                            </p>
                        </div>
                        <span class="ml-auto text-xs font-mono text-slate-400" id="fb-last-read">--</span>
                    </div>
                </div>
            </section>

        </div>

        {{-- ── Right Column ────────────────────────────────────── --}}
        <div class="md:col-span-4 space-y-8">

            {{-- User Profile --}}
            <section class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="h-24 bg-gradient-to-r from-primary to-secondary relative"></div>
                <div class="px-6 pb-6 pt-10 relative">
                    <div class="absolute -top-10 left-6 h-20 w-20 rounded-xl border-4 border-white bg-primary flex items-center justify-center shadow-md">
                        @if(auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" class="w-full h-full rounded-xl object-cover">
                        @else
                            <span class="text-white font-display font-bold text-3xl">{{ substr(auth()->user()->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="mb-4">
                        <h3 class="font-headline-md text-on-surface">{{ auth()->user()->name }}</h3>
                        <p class="text-body-sm text-on-surface-variant">{{ auth()->user()->email }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                                System Administrator
                            </span>
                            @if(auth()->user()->google_id)
                            <span class="text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">
                                Gmail connected
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="space-y-2 pt-4 border-t border-slate-100 text-sm text-slate-500">
                        <div class="flex justify-between">
                            <span>Member since</span>
                            <span class="font-semibold text-on-surface">{{ auth()->user()->created_at->format('M Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Account ID</span>
                            <span class="font-mono text-xs">#{{ auth()->user()->id }}</span>
                        </div>
                        @if($device)
                        <div class="flex justify-between">
                            <span>Primary device</span>
                            <span class="font-semibold text-xs text-on-surface">{{ $device->name }}</span>
                        </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full py-2.5 border border-error text-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">logout</span> Sign Out
                        </button>
                    </form>
                </div>
            </section>

            {{-- Current thresholds summary --}}
            @if($device)
            <section class="bg-white border border-outline-variant rounded-xl p-6">
                <h3 class="font-title-sm text-title-sm text-on-surface mb-4">Active Thresholds</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>Warning
                        </span>
                        <span class="font-data-num text-lg text-amber-600" id="active-warn">{{ $warn }}%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>Critical
                        </span>
                        <span class="font-data-num text-lg text-red-600" id="active-crit">{{ $crit }}%</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-400" style="font-size:14px">science</span>Silica interval
                        </span>
                        <span class="font-semibold text-sm text-on-surface" id="active-silica">{{ $silicaDays }}d</span>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">Values shown reflect what's saved in the database and used by the scheduler.</p>
            </section>
            @endif

            {{-- Danger Zone --}}
            <section class="bg-error-container/10 border border-error/20 rounded-xl p-6">
                <h2 class="font-title-sm text-title-sm text-error mb-2">Danger Zone</h2>
                <p class="text-body-sm text-on-error-container/70 mb-4">Permanent actions that cannot be reversed.</p>
                <div class="space-y-2">
                    @if($device)
                    <button
                        type="button"
                        onclick="confirmDelete({{ $device->id }}, '{{ addslashes($device->name) }}')"
                        class="w-full py-2.5 text-error border border-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">delete</span> Remove Device
                    </button>
                    @endif
                    <button type="button" onclick="if(confirm('Are you sure? This cannot be undone.'))" class="w-full py-2.5 text-error border border-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all">
                        Factory Reset Hub
                    </button>
                </div>
            </section>

        </div>
    </div>
    </form>{{-- end #settings-form --}}

</div>

{{-- Sticky Save Bar --}}
<div id="save-bar" class="fixed bottom-0 left-0 lg:left-64 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 px-6 py-4 flex items-center justify-between gap-4 z-40 translate-y-full transition-transform duration-300">
    <p class="text-sm text-slate-500 flex items-center gap-2">
        <span class="material-symbols-outlined text-amber-500 text-base">edit</span>
        You have unsaved threshold changes
    </p>
    <div class="flex items-center gap-3">
        <button type="button" onclick="resetThresholds()" class="px-5 py-2 text-slate-500 font-semibold text-sm hover:text-on-surface transition-colors">Cancel</button>
        <button type="button" onclick="saveThresholds()" class="px-7 py-2 bg-primary text-white rounded-lg font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">save</span> Save Changes
        </button>
    </div>
</div>

@endsection

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

// ── Status helpers ────────────────────────────────────────────────
function getStatusMeta(statusStr, humidity) {
    const s = (statusStr || '').toLowerCase();
    if (s.includes('critical') || humidity > CRIT_THRESH) {
        return { label:'CRITICAL', badge:'bg-red-100 text-red-700',      bar:'bg-red-500',     topBar:'bg-red-500',     dot:'bg-red-500',     text:'text-red-600' };
    } else if (s.includes('warning') || humidity > WARN_THRESH) {
        return { label:'WARNING',  badge:'bg-amber-100 text-amber-700',   bar:'bg-amber-500',   topBar:'bg-amber-500',   dot:'bg-amber-500',   text:'text-amber-600' };
    }
    return     { label:'SAFE',     badge:'bg-emerald-100 text-emerald-700', bar:'bg-silica-500', topBar:'bg-emerald-500', dot:'bg-emerald-500', text:'text-emerald-600' };
}

// ── Connection indicator (top pill + Firebase Connection card) ────
db.ref('.info/connected').on('value', snap => {
    const live  = snap.val() === true;
    const dot   = document.getElementById('conn-dot');
    const label = document.getElementById('conn-label');
    if (dot) {
        dot.className   = `w-2 h-2 rounded-full ${live ? 'bg-emerald-500' : 'bg-amber-400'} animate-pulse`;
    }
    if (label) {
        label.textContent = live ? 'Live' : 'Reconnecting…';
        label.className   = `font-medium ${live ? 'text-emerald-600' : 'text-amber-500'}`;
    }

    const fbDot = document.getElementById('fb-dot');
    const fbTxt = document.getElementById('fb-status-text');
    if (fbDot) fbDot.className = `w-3 h-3 rounded-full ${live ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400 animate-pulse'}`;
    if (fbTxt) fbTxt.textContent = live ? 'Connected — Receiving live data' : 'Reconnecting to Firebase…';
});

// ── Update live readings ────────────────────────────────────────────
function updateReadings(data) {
    const hum    = parseFloat(data.humidity    ?? 0);
    const temp   = parseFloat(data.temperature ?? 0);
    const status = data.status || 'Unknown';
    const now    = new Date().toLocaleTimeString();
    const meta   = getStatusMeta(status, hum);

    const topBar = document.getElementById('topbar');
    if (topBar) topBar.className = `h-1.5 transition-colors ${meta.topBar}`;

    const badge = document.getElementById('badge');
    if (badge) {
        badge.className = `text-xs font-bold px-2.5 py-1 rounded-full ${meta.badge} inline-flex items-center gap-1.5 mb-2`;
        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${meta.dot} animate-pulse"></span>${meta.label}`;
    }

    const tempEl = document.getElementById('stat-temp');
    if (tempEl) tempEl.innerHTML = `${temp.toFixed(1)}<span class="text-base text-slate-400">°C</span>`;

    const humEl = document.getElementById('stat-hum');
    if (humEl) humEl.innerHTML = `${hum.toFixed(1)}<span class="text-base text-slate-400">%</span>`;

    const humPct = document.getElementById('hum-pct');
    if (humPct) humPct.textContent = `${hum.toFixed(1)}%`;

    const bar = document.getElementById('bar');
    if (bar) {
        bar.style.width = `${Math.min(hum, 100)}%`;
        bar.className   = `h-full ${meta.bar} rounded-full transition-all duration-700`;
    }

    const timeEl = document.getElementById('time');
    if (timeEl) timeEl.textContent = now;

    const fbLastRead = document.getElementById('fb-last-read');
    if (fbLastRead) fbLastRead.textContent = now;
}

@if($device)
db.ref('{{ $device->firebase_path }}').on('value', snapshot => {
    const data = snapshot.val();
    if (data) updateReadings(data);
});
@endif

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

        btn.className     = `relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors ${armed ? 'bg-purple-500' : 'bg-slate-200'}`;
        btn.dataset.armed = armed ? 'true' : 'false';
        const knob = btn.querySelector('span');
        if (knob) knob.className = `inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${armed ? 'translate-x-4' : 'translate-x-0.5'}`;

        const label = document.getElementById('protection-label');
        if (label) label.textContent = armed ? 'Armed — tamper alerts on' : 'Disarmed';

        const icon = document.getElementById('protection-icon');
        if (icon) icon.className = icon.className.replace(/text-(purple|slate)-(500|300)/, armed ? 'text-purple-500' : 'text-slate-300');

    } catch (e) {
        console.error('toggleProtection error:', e);
    } finally {
        btn.disabled = false;
    }
}

// ── Threshold sliders ─────────────────────────────────────────────
const critSlider = document.getElementById('crit-slider');
const warnSlider = document.getElementById('warn-slider');
const critVal    = document.getElementById('crit-val');
const warnVal    = document.getElementById('warn-val');
const saveBar    = document.getElementById('save-bar');

// Server-side initial values (fallback if sliders are disabled)
const initialCrit = {{ $crit }};
const initialWarn = {{ $warn }};

let dirty = false;

function updateMarkers() {
    const c = critSlider?.value ?? initialCrit;
    const w = warnSlider?.value ?? initialWarn;
    if (critVal) critVal.textContent = c + '%';
    if (warnVal) warnVal.textContent = w + '%';

    const critMarker = document.getElementById('crit-marker');
    const warnMarker = document.getElementById('warn-marker');
    if (critMarker) { critMarker.style.left = c + '%'; document.getElementById('crit-label').textContent = c + '%'; }
    if (warnMarker) { warnMarker.style.left = w + '%'; document.getElementById('warn-label').textContent = w + '%'; }

    // Update live summary sidebar
    const ac = document.getElementById('active-crit');
    const aw = document.getElementById('active-warn');
    if (ac) ac.textContent = c + '%';
    if (aw) aw.textContent = w + '%';

    if (!dirty) { dirty = true; if (saveBar) saveBar.style.transform = 'translateY(0)'; }
}

if (critSlider) critSlider.addEventListener('input', updateMarkers);
if (warnSlider) warnSlider.addEventListener('input', updateMarkers);

// Also trigger dirty flag on device settings fields
['notify-emails', 'temp-min', 'temp-max', 'alert-cooldown'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => {
        if (!dirty) { dirty = true; if (saveBar) saveBar.style.transform = 'translateY(0)'; }
    });
});

window.resetThresholds = function() {
    if (critSlider) critSlider.value = initialCrit;
    if (warnSlider) warnSlider.value = initialWarn;
    // Reset emails + temp range + cooldown
    const ne = document.getElementById('notify-emails');
    const tmin = document.getElementById('temp-min');
    const tmax = document.getElementById('temp-max');
    const cd = document.getElementById('alert-cooldown');
    if (ne) ne.value = '{{ addslashes($notifyList) }}';
    if (tmin) tmin.value = '{{ $settings?->temp_min }}';
    if (tmax) tmax.value = '{{ $settings?->temp_max }}';
    if (cd) cd.value = {{ $settings?->alert_cooldown_minutes ?? 30 }};
    dirty = false;
    if (saveBar) saveBar.style.transform = 'translateY(100%)';
    updateMarkers();
    dirty = false;
};

window.saveThresholds = function() {
    // Also keep localStorage in sync so this page's live JS picks up new thresholds
    if (critSlider) localStorage.setItem('critThreshold', critSlider.value);
    if (warnSlider) localStorage.setItem('warnThreshold', warnSlider.value);
    document.getElementById('settings-form').submit();
};

// ── Show/hide API key ─────────────────────────────────────────────
window.toggleKey = function() {
    const field = document.getElementById('api-key-field');
    const icon  = document.getElementById('key-eye-icon');
    field.type  = field.type === 'password' ? 'text' : 'password';
    icon.textContent = field.type === 'password' ? 'visibility' : 'visibility_off';
};

// ── Test connection ─────────────────────────────────────────────────
window.testConnection = function() {
    const btn = document.getElementById('test-btn');
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">autorenew</span> Testing…';
    btn.disabled = true;
    const el = document.getElementById('conn-result');
    el.classList.remove('hidden');

    db.ref('.info/connected').once('value').then(snap => {
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Connection';
        btn.disabled = false;
        const ok = snap.val() === true;
        el.className = `p-4 rounded-xl border flex items-start gap-3 ${ok ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'}`;
        document.getElementById('conn-icon').textContent = ok ? 'check_circle' : 'error';
        document.getElementById('conn-icon').className   = `material-symbols-outlined mt-0.5 flex-shrink-0 ${ok ? 'text-emerald-500' : 'text-red-500'}`;
        document.getElementById('conn-title').textContent = ok ? 'Connection Successful' : 'Connection Failed';
        document.getElementById('conn-title').className  = `font-semibold text-sm ${ok ? 'text-emerald-700' : 'text-red-700'}`;
        document.getElementById('conn-desc').textContent  = ok
            ? 'Firebase Realtime Database is reachable and responding.'
            : 'Could not reach Firebase. Check your API key and database URL.';
        document.getElementById('conn-desc').className   = `text-xs mt-0.5 ${ok ? 'text-emerald-600' : 'text-red-600'}`;
    }).catch(() => {
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Connection';
        btn.disabled = false;
    });
};

// ── ESC to close the Add Unit modal ────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeAddModal();
});
</script>
@endsection
