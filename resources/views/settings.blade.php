@extends('layouts.app')

@section('title', 'Settings | DryBox AI')

@section('content')
<header class="mb-10">
    <h1 class="font-display-lg text-display-lg text-blue-900 mb-2">System Configuration</h1>
    <p class="font-body-base text-on-surface-variant">Manage global thresholds, hardware connectivity, and administrative access.</p>
</header>

<div class="grid grid-cols-1 md:grid-cols-12 gap-8">
    <!-- Bento Grid: Column 1 (Large - Main Settings) -->
    <div class="md:col-span-8 space-y-8">
        <!-- Alerts & Thresholds Section -->
        <section class="bg-white border border-outline-variant rounded-xl p-8">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary">notification_important</span>
                <h2 class="font-headline-md text-headline-md text-on-surface">Humidity & Environmental Alerts</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <label class="block font-label-caps text-label-caps text-on-surface-variant">CRITICAL HUMIDITY THRESHOLD</label>
                    <div class="flex items-center gap-4">
                        <input class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-primary" max="80" min="20" type="range" value="55"/>
                        <span class="font-data-num text-headline-md text-primary min-w-[60px]">55%</span>
                    </div>
                    <p class="text-body-sm text-outline">System will trigger critical status if RH exceeds this value.</p>
                </div>
                <div class="space-y-4">
                    <label class="block font-label-caps text-label-caps text-on-surface-variant">TEMP DEVIATION LIMIT</label>
                    <div class="flex items-center gap-4">
                        <input class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-primary" max="10" min="1" type="range" value="3"/>
                        <span class="font-data-num text-headline-md text-primary min-w-[60px]">±3°C</span>
                    </div>
                    <p class="text-body-sm text-outline">Alert if temperature fluctuates beyond the baseline.</p>
                </div>
            </div>
        </section>

        <!-- Hardware & Connectivity -->
        <section class="bg-white/80 backdrop-blur-sm border border-outline-variant rounded-xl p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16"></div>
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary">router</span>
                <h2 class="font-headline-md text-headline-md text-on-surface">Hardware Configuration</h2>
            </div>
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block font-label-caps text-label-caps text-on-surface-variant">WI-FI SSID (GLOBAL)</label>
                        <input class="w-full px-4 py-2 border border-outline-variant rounded-lg font-body-base" type="text" value="DryBox_Secure_Internal"/>
                    </div>
                    <div class="space-y-2">
                        <label class="block font-label-caps text-label-caps text-on-surface-variant">API ENDPOINT</label>
                        <input class="w-full px-4 py-2 border border-outline-variant rounded-lg font-body-base" type="text" value="https://api.drybox-ai.v1.internal"/>
                    </div>
                </div>
                <div class="p-6 bg-surface-container-low rounded-lg border border-outline-variant/50">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block font-label-caps text-label-caps text-primary">FIREBASE API KEY</label>
                        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Connected</span>
                    </div>
                    <div class="flex gap-2">
                        <input class="flex-1 px-4 py-2 bg-white border border-outline-variant rounded-lg font-mono text-sm" type="password" value="AIzaSyB_k8H2jL9mN4oP3qR1sT5uV7wX9yZ"/>
                        <button class="px-4 py-2 bg-primary-container text-on-primary-container rounded-lg font-semibold text-sm hover:opacity-80">Verify</button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Bento Grid: Column 2 (Smaller - Access & Profile) -->
    <div class="md:col-span-4 space-y-8">
        <!-- Profile Management -->
        <section class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="h-24 bg-gradient-to-r from-primary to-secondary relative"></div>
            <div class="px-6 pb-6 pt-10 relative">
                <div class="absolute -top-10 left-6 h-20 w-20 rounded-xl border-4 border-white bg-surface-container-high overflow-hidden shadow-sm">
                    <img alt="Alex Thompson" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCT_3St2LLJEk9q0i8IclAccOWUwi1cmGIlAeh9z9_9QUVk5-pj9nrqoUs7AcoXehXq9Ny8a7srFLBv3IIliJSPsLAV4QiSy9ktarv4ie5Fudqj_xz-meUfEi4WP00ikNa6bheeYOXDwmjIJ1t1OsuPiuqTIaHrAzatx46qleJTCy5rpKAPfUl254gx1appc0VoRlfJE13JZFkzKoaMFS1tst9ISLpAB1FetMSaT9dQu7lYO8k4EHycuYixA1aYVurDRLIcL40HBgY"/>
                </div>
                <div>
                    <h3 class="font-headline-md text-on-surface">Alex Thompson</h3>
                    <p class="text-body-sm text-on-surface-variant">System Administrator</p>
                </div>
                <button class="w-full mt-6 py-2 bg-surface-container border border-outline-variant rounded-lg font-semibold text-sm">Edit Profile</button>
            </div>
        </section>

        <!-- Danger Zone -->
        <section class="bg-error-container/20 border border-error/20 rounded-xl p-6">
            <h2 class="font-title-sm text-title-sm text-error mb-2">Danger Zone</h2>
            <p class="text-body-sm text-on-error-container/70 mb-4">Permanent actions that cannot be reversed.</p>
            <button class="w-full py-2 text-error border border-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all">
                Factory Reset Hub
            </button>
        </section>
    </div>
</div>

<!-- Sticky Save Bar -->
<div class="fixed bottom-0 left-0 lg:left-64 right-0 bg-white/90 backdrop-blur-md border-t border-slate-200 px-6 py-4 flex items-center justify-end gap-4 z-40">
    <button class="px-6 py-2 text-slate-500 font-semibold text-sm">Cancel</button>
    <button class="px-8 py-2 bg-primary text-white rounded-lg font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
        Save Changes
    </button>
</div>
@endsection
