@extends('layouts.app')

@section('title', 'DryBox AI - Dashboard')

@section('content')
<div class="max-w-7xl mx-auto space-y-md">
    <!-- Dashboard Header & Action -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">System Dashboard</h1>
            <p class="font-body-base text-body-base text-on-surface-variant">Monitoring <span id="total-units-count">12</span> dry storage units across 2 facilities.</p>
        </div>
        <button class="flex items-center justify-center gap-2 bg-secondary text-on-secondary px-6 py-3 rounded-xl font-bold transition-all hover:shadow-lg active:scale-95">
            <span class="material-symbols-outlined">add_circle</span>
            Add New Box
        </button>
    </div>

    <!-- Alerts Summary Bento -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-grid-gutter">
        <div class="md:col-span-2 bg-error-container/20 border border-error/20 p-md rounded-xl flex items-start gap-4">
            <div class="p-3 bg-error rounded-full text-on-error">
                <span class="material-symbols-outlined">warning</span>
            </div>
            <div>
                <h3 class="font-headline-md text-headline-md text-on-error-container">Critical Alert: Box 4</h3>
                <p class="font-body-sm text-body-sm text-on-error-container mt-1">Humidity has exceeded 45% for over 30 minutes. Silica saturation suspected.</p>
                <div class="mt-4 flex gap-3">
                    <button class="px-4 py-2 bg-error text-on-error font-bold text-xs rounded-lg uppercase tracking-wider">Inspect Box</button>
                    <button class="px-4 py-2 bg-white/50 text-on-error-container font-bold text-xs rounded-lg uppercase tracking-wider border border-error/10">Dismiss</button>
                </div>
            </div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant p-md rounded-xl flex flex-col justify-between">
            <div>
                <span class="font-label-caps text-label-caps text-on-surface-variant">SYSTEM STATUS</span>
                <div class="flex items-center gap-2 mt-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-500" id="global-status-indicator"></div>
                    <span class="font-headline-md text-headline-md text-on-surface" id="global-status-text">Optimal</span>
                </div>
            </div>
            <div class="mt-4 border-t border-outline-variant pt-4 flex justify-between items-center">
                <span class="font-body-sm text-body-sm text-on-surface-variant">Last Update: <span id="last-update-time">Just now</span></span>
                <span class="material-symbols-outlined text-secondary">cloud_done</span>
            </div>
        </div>
    </section>

    <!-- Grid of Dry Box Cards -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-grid-gutter" id="boxes-container">
        <div class="col-span-full py-20 flex flex-col items-center justify-center text-slate-400">
            <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
            <p class="font-headline-md">Connecting to AI sensor network...</p>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

<script>
    const firebaseConfig = {
        apiKey: "AIzaSyB_k8H2jL9mN4oP3qR1sT5uV7wX9yZ",
        databaseURL: "https://drybox-ai-default-rtdb.firebaseio.com",
    };

    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }
    const database = firebase.database();

    const boxesContainer = document.getElementById('boxes-container');
    const totalUnitsCount = document.getElementById('total-units-count');
    const lastUpdateTime = document.getElementById('last-update-time');
    const globalStatusIndicator = document.getElementById('global-status-indicator');
    const globalStatusText = document.getElementById('global-status-text');

    function renderBox(id, data) {
        const humidity = data.humidity || 0;
        const temperature = data.temperature || 0;
        const silica = data.silica || 'N/A';
        const name = data.name || `Box ${id}`;
        
        let status = 'SAFE';
        let statusClass = 'bg-emerald-100 text-emerald-800';
        let barColor = 'bg-emerald-500';
        let borderClass = 'bg-emerald-500';

        if (humidity > 45) {
            status = 'CRITICAL';
            statusClass = 'bg-error-container text-on-error-container';
            barColor = 'bg-error';
            borderClass = 'bg-error';
        } else if (humidity > 35) {
            status = 'WARNING';
            statusClass = 'bg-amber-100 text-amber-800';
            barColor = 'bg-amber-500';
            borderClass = 'bg-amber-500';
        }

        return `
            <div class="bg-white border border-outline-variant rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                <div class="h-1 ${borderClass}"></div>
                <div class="p-md space-y-md">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-headline-md text-headline-md text-primary">Box ${id}</h4>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">${name}</p>
                        </div>
                        <span class="px-3 py-1 ${statusClass} font-label-caps text-[10px] rounded-full">${status}</span>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="font-data-num text-data-num text-on-background">${humidity}<span class="text-2xl">%</span></span>
                        <div class="pb-2">
                            <span class="font-label-caps text-label-caps text-on-surface-variant block">HUMIDITY</span>
                            <div class="w-32 h-2 bg-slate-100 rounded-full mt-1 overflow-hidden">
                                <div class="h-full ${barColor} transition-all duration-1000" style="width: ${humidity}%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                        <div>
                            <span class="font-label-caps text-label-caps text-on-surface-variant">TEMP</span>
                            <p class="font-title-sm text-title-sm text-on-surface">${temperature} °C</p>
                        </div>
                        <div>
                            <span class="font-label-caps text-label-caps text-on-surface-variant">SILICA LIFE</span>
                            <p class="font-title-sm text-title-sm ${silica === 'EXPIRED' ? 'text-error' : 'text-on-surface'}">${silica} ${silica === 'EXPIRED' ? '' : 'Days'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    database.ref('boxes').on('value', (snapshot) => {
        const boxes = snapshot.val();
        if (boxes) {
            let html = '';
            const boxIds = Object.keys(boxes);
            boxIds.forEach(id => {
                html += renderBox(id, boxes[id]);
            });
            
            html += `
                <div class="border-2 border-dashed border-outline-variant rounded-xl flex flex-col items-center justify-center p-md text-on-surface-variant hover:bg-slate-50 transition-colors cursor-pointer group">
                    <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-4xl">add</span>
                    </div>
                    <span class="font-headline-md text-headline-md">Add New Unit</span>
                    <p class="font-body-sm text-body-sm mt-1">Configure a new AI sensor</p>
                </div>
            `;
            
            boxesContainer.innerHTML = html;
            totalUnitsCount.innerText = boxIds.length;
            lastUpdateTime.innerText = new Date().toLocaleTimeString();
            
            const anyCritical = Object.values(boxes).some(b => b.humidity > 45);
            if (anyCritical) {
                globalStatusIndicator.className = 'w-2.5 h-2.5 rounded-full bg-error animate-pulse';
                globalStatusText.innerText = 'Critical Alert';
                globalStatusText.className = 'font-headline-md text-headline-md text-error';
            } else {
                globalStatusIndicator.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500';
                globalStatusText.innerText = 'Optimal';
                globalStatusText.className = 'font-headline-md text-headline-md text-on-surface';
            }
        }
    });
</script>
@endsection
