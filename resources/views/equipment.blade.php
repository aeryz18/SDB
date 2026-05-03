@extends('layouts.app')

@section('title', 'Equipment Inventory | DryBox AI')

@section('content')
<div class="flex justify-between items-end mb-10">
    <div>
        <h1 class="font-display-lg text-3xl font-bold text-primary mb-2">Equipment Inventory</h1>
        <p class="text-slate-500">Manage and monitor all dry storage units in your facility.</p>
    </div>
    <button class="bg-primary text-white px-6 py-2.5 rounded-xl font-bold flex items-center gap-2 hover:opacity-90 transition-all">
        <span class="material-symbols-outlined text-sm">add</span>
        Register New Unit
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <!-- Equipment Item 1 -->
    <div class="bg-white border border-outline-variant rounded-xl p-6 hover:shadow-md transition-shadow">
        <div class="flex justify-between mb-4">
            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">CONNECTED</span>
            <button class="text-slate-400 hover:text-primary"><span class="material-symbols-outlined">more_vert</span></button>
        </div>
        <h3 class="font-display-lg text-xl font-bold text-primary mb-1">BOX-A244</h3>
        <p class="text-sm text-slate-500 mb-6">Location: Lab A - Shelf 2</p>
        <div class="space-y-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Last Maintenance</span>
                <span class="font-semibold">Oct 24, 2024</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Desiccant Status</span>
                <span class="text-emerald-600 font-semibold">92% Health</span>
            </div>
        </div>
        <button class="w-full mt-6 py-2 bg-slate-50 text-primary font-bold text-sm rounded-lg hover:bg-primary/5 transition-colors">View Details</button>
    </div>

    <!-- Equipment Item 2 -->
    <div class="bg-white border border-outline-variant rounded-xl p-6 hover:shadow-md transition-shadow">
        <div class="flex justify-between mb-4">
            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded">MAINTENANCE DUE</span>
            <button class="text-slate-400 hover:text-primary"><span class="material-symbols-outlined">more_vert</span></button>
        </div>
        <h3 class="font-display-lg text-xl font-bold text-primary mb-1">BOX-S012</h3>
        <p class="text-sm text-slate-500 mb-6">Location: Studio 1 - Main Rack</p>
        <div class="space-y-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Last Maintenance</span>
                <span class="font-semibold">Aug 12, 2024</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Desiccant Status</span>
                <span class="text-amber-600 font-semibold">12% Health</span>
            </div>
        </div>
        <button class="w-full mt-6 py-2 bg-slate-50 text-primary font-bold text-sm rounded-lg hover:bg-primary/5 transition-colors">View Details</button>
    </div>
</div>
@endsection
