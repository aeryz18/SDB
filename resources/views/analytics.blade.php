@extends('layouts.app')

@section('title', 'Analytics & Historical Trends | DryBox AI')

@section('content')
<div class="max-w-7xl mx-auto space-y-grid-gutter">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <span class="font-label-caps text-label-caps text-secondary mb-2 block uppercase">System-wide performance</span>
            <h1 class="font-display-lg text-display-lg text-on-surface tracking-tight">Analytics & Trends</h1>
        </div>
        <div class="flex items-center gap-base">
            <button class="flex items-center gap-2 px-4 py-2 border border-outline-variant bg-white rounded-xl text-sm font-semibold text-on-surface hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-sm">calendar_today</span>
                Last 30 Days
            </button>
            <button class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-colors">
                <span class="material-symbols-outlined text-sm">download</span>
                Export Report
            </button>
        </div>
    </div>

    <!-- Bento Grid - Historical Performance -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-grid-gutter">
        <!-- Main Comparison Chart -->
        <div class="lg:col-span-8 bg-white border border-outline-variant rounded-xl p-md flex flex-col min-h-[400px]">
            <div class="flex justify-between items-center mb-xl">
                <div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Humidity Trends (RH%)</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Comparative analysis across all active storage units</p>
                </div>
                <div class="flex gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-primary"></span>
                        <span class="text-xs font-semibold text-on-surface-variant uppercase">Box Alpha</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-secondary-container"></span>
                        <span class="text-xs font-semibold text-on-surface-variant uppercase">Box Sigma</span>
                    </div>
                </div>
            </div>
            <!-- Visual Chart Placeholder -->
            <div class="flex-1 relative flex items-end gap-1">
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                    <div class="border-t border-slate-100 w-full h-px"></div>
                    <div class="border-t border-slate-100 w-full h-px"></div>
                    <div class="border-t border-slate-100 w-full h-px"></div>
                    <div class="border-t border-slate-100 w-full h-px"></div>
                    <div class="border-t border-slate-100 w-full h-px"></div>
                </div>
                <div class="w-full h-48 relative overflow-hidden">
                    <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 1000 200">
                        <path class="text-primary opacity-80" d="M0,150 Q100,140 200,160 T400,130 T600,150 T800,110 T1000,140" fill="none" stroke="currentColor" stroke-width="3"></path>
                        <path class="text-primary opacity-5" d="M0,150 Q100,140 200,160 T400,130 T600,150 T800,110 T1000,140 V200 H0 Z" fill="currentColor"></path>
                        <path class="text-secondary-container opacity-80" d="M0,180 Q150,170 300,185 T600,165 T900,175 T1000,160" fill="none" stroke="currentColor" stroke-width="3"></path>
                        <path class="text-secondary-container opacity-5" d="M0,180 Q150,170 300,185 T600,165 T900,175 T1000,160 V200 H0 Z" fill="currentColor"></path>
                    </svg>
                </div>
            </div>
            <div class="flex justify-between pt-sm text-label-caps text-outline uppercase">
                <span>Day 01</span>
                <span>Day 07</span>
                <span>Day 14</span>
                <span>Day 21</span>
                <span>Day 30</span>
            </div>
        </div>

        <!-- Forecast Section -->
        <div class="lg:col-span-4 bg-tertiary-container text-on-tertiary-container rounded-xl p-md border border-tertiary shadow-lg flex flex-col justify-between overflow-hidden relative">
            <div class="relative z-10">
                <span class="inline-block px-2 py-1 bg-white/20 rounded-lg text-[10px] font-bold uppercase tracking-widest mb-4">AI Predictive Model</span>
                <h3 class="font-headline-md text-headline-md mb-2">7-Day Forecast</h3>
                <p class="font-body-sm text-body-sm text-white/70 mb-xl">Humidity levels are expected to remain stable across 94% of units.</p>
                <div class="space-y-sm">
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg backdrop-blur-sm border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">trending_down</span>
                            <span class="text-sm font-medium">Avg. Forecast</span>
                        </div>
                        <span class="font-data-num text-2xl">22%</span>
                    </div>
                    <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg backdrop-blur-sm border border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-on-tertiary-container">warning</span>
                            <span class="text-sm font-medium">Risk Factor</span>
                        </div>
                        <span class="font-data-num text-2xl text-tertiary-fixed-dim">Low</span>
                    </div>
                </div>
            </div>
            <div class="absolute -right-12 -bottom-8 opacity-20 transform -rotate-12 pointer-events-none">
                <span class="material-symbols-outlined text-[160px]" style="font-variation-settings: 'wght' 200;">query_stats</span>
            </div>
        </div>
    </section>

    <!-- Lower Section: Maintenance & Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-grid-gutter">
        <div class="lg:col-span-2 bg-white border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-md border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-title-sm text-title-sm text-on-surface">Recent Alerts & Maintenance</h3>
                <button class="text-xs font-bold text-primary uppercase tracking-widest hover:underline">View All Logs</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="px-md py-sm font-label-caps text-label-caps text-outline uppercase">Box ID</th>
                            <th class="px-md py-sm font-label-caps text-label-caps text-outline uppercase">Event Type</th>
                            <th class="px-md py-sm font-label-caps text-label-caps text-outline uppercase">Status</th>
                            <th class="px-md py-sm font-label-caps text-label-caps text-outline uppercase">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-md py-4 text-sm font-semibold text-blue-900">BOX-A244</td>
                            <td class="px-md py-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm text-error">water_drop</span>
                                    <span class="text-sm font-body-sm">Humidity Spike (34%)</span>
                                </div>
                            </td>
                            <td class="px-md py-4">
                                <span class="px-2 py-1 bg-error-container text-on-error-container text-[10px] font-bold uppercase rounded-lg">Critical</span>
                            </td>
                            <td class="px-md py-4 text-xs text-slate-500 font-body-sm">Oct 24, 14:22</td>
                        </tr>
                        <!-- More rows... -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-md flex flex-col justify-between">
            <div>
                <h3 class="font-title-sm text-title-sm text-on-surface mb-sm">AI Optimization Insight</h3>
                <div class="p-4 bg-primary-fixed text-on-primary-fixed-variant rounded-xl border border-primary-fixed-dim">
                    <p class="text-sm font-medium leading-relaxed">Reducing door-open time on <span class="font-bold">Unit Alpha</span> by 15% could extend desiccant life by 4 months.</p>
                </div>
            </div>
            <div class="mt-md pt-md border-t border-slate-100">
                <div class="flex justify-between items-end">
                    <div>
                        <span class="font-label-caps text-label-caps text-outline uppercase block mb-1">Total Savings</span>
                        <span class="font-data-num text-3xl text-on-surface">$1,420</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-primary-container bg-primary px-1.5 py-0.5 rounded text-white">+12%</span>
                        <span class="block text-[10px] text-outline uppercase mt-1">vs last month</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
