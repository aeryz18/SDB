@extends('layouts.setup')

@section('title', 'Add Your Device')

@php $currentStep = 1; @endphp

@section('content')

<div class="mb-8">
  <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-full text-blue-700 text-xs font-bold uppercase tracking-wider mb-4">
    <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
    Account created successfully
  </div>
  <h1 class="font-display font-bold text-3xl text-slate-900 mb-2">Register your DryBox unit</h1>
  <p class="text-slate-500">Give your device a name and set the Firebase path — this must match the <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono text-slate-700">FIREBASE_PATH</code> in your ESP32 firmware.</p>
</div>

{{-- Validation errors --}}
@if($errors->any())
<div class="mb-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
  <span class="material-symbols-outlined text-base mt-0.5">error</span>
  <div>
    @foreach($errors->all() as $error)
      <p>{{ $error }}</p>
    @endforeach
  </div>
</div>
@endif

<form method="POST" action="{{ route('setup.device.store') }}" class="space-y-5">
  @csrf

  {{-- Unit name --}}
  <div>
    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
      Unit Name <span class="text-red-400">*</span>
    </label>
    <input
      name="name"
      type="text"
      placeholder="e.g. DryBox Unit 1"
      required
      value="{{ old('name', 'DryBox Unit 1') }}"
      class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-colors bg-white"
    >
    <p class="text-xs text-slate-400 mt-1.5">A friendly name shown on your dashboard.</p>
  </div>

  {{-- Location --}}
  <div>
    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
      Location <span class="text-slate-400 font-normal">(optional)</span>
    </label>
    <input
      name="location"
      type="text"
      placeholder="e.g. Studio Room, Shelf A"
      value="{{ old('location') }}"
      class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-colors bg-white"
    >
  </div>

  {{-- Firebase path --}}
  <div>
    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
      Firebase Path <span class="text-red-400">*</span>
    </label>
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-mono select-none">rtdb:/</span>
      <input
        name="firebase_path"
        type="text"
        placeholder="drybox"
        required
        value="{{ old('firebase_path', 'drybox') }}"
        class="w-full pl-16 pr-4 py-3 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-colors bg-white"
      >
    </div>

    {{-- Explanation box --}}
    <div class="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
      <div class="flex items-start gap-2.5">
        <span class="material-symbols-outlined text-amber-600 text-base mt-0.5">info</span>
        <div class="text-xs text-amber-800 leading-relaxed">
          <p class="font-semibold mb-1">This must match your ESP32 firmware</p>
          <p>In your <code class="bg-amber-100 px-1 rounded font-mono">drybox_esp32.ino</code>, the line:</p>
          <pre class="mt-1.5 bg-amber-100 rounded px-2 py-1.5 font-mono overflow-x-auto">#define FIREBASE_PATH  "/drybox"</pre>
          <p class="mt-1.5">The part after <code class="bg-amber-100 px-1 rounded font-mono">/</code> must match what you enter here.
          If you leave it as <code class="bg-amber-100 px-1 rounded font-mono">drybox</code>, no change is needed in the firmware.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button
      type="submit"
      class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
    >
      Add Device &amp; Continue
      <span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span>
    </button>
  </div>
</form>

{{-- Already have a device link --}}
<p class="text-center text-sm text-slate-400 mt-6">
  Already set up? <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline font-medium">Go to dashboard</a>
</p>

@endsection
