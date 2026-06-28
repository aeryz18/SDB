@extends('layouts.setup')

@section('title', 'Flash Your Firmware')

@php $currentStep = 2; @endphp

@section('content')

<div class="mb-8">
  <h1 class="font-display font-bold text-3xl text-slate-900 mb-2">Flash your ESP32 firmware</h1>
  <p class="text-slate-500">Open <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono text-slate-700">drybox_esp32.ino</code> in Arduino IDE and replace the CONFIG section with the values below. Then upload to your ESP32.</p>
</div>

{{-- Step 1: Update config --}}
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">1</div>
    <h2 class="font-semibold text-slate-800">Update the CONFIG section</h2>
  </div>
  <div class="p-5">
    <p class="text-sm text-slate-500 mb-3">Find the <code class="bg-slate-100 px-1 rounded font-mono text-xs">// ─── CONFIG ───</code> block near the top of the file and replace it with this:</p>

    <div class="relative group">
      <pre id="config-block" class="bg-slate-900 text-slate-200 rounded-xl p-4 text-xs font-mono leading-relaxed overflow-x-auto"><span class="text-slate-500">// ─── CONFIG ───────────────────────────────────────────────────────────────────</span>
<span class="text-green-400">#define</span> <span class="text-blue-300">WIFI_SSID</span>      <span class="text-amber-300">"<span class="text-red-300">YOUR_WIFI_NAME</span>"</span>
<span class="text-green-400">#define</span> <span class="text-blue-300">WIFI_PASSWORD</span>  <span class="text-amber-300">"<span class="text-red-300">YOUR_WIFI_PASSWORD</span>"</span>
<span class="text-green-400">#define</span> <span class="text-blue-300">API_KEY</span>        <span class="text-amber-300">"{{ $apiKey }}"</span>
<span class="text-green-400">#define</span> <span class="text-blue-300">DATABASE_URL</span>   <span class="text-amber-300">"{{ $dbUrl }}"</span>
<span class="text-green-400">#define</span> <span class="text-blue-300">FIREBASE_PATH</span>  <span class="text-amber-300">"/{{ $device->firebase_path }}"</span></pre>

      <button
        onclick="copyConfig()"
        class="absolute top-3 right-3 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs rounded-lg flex items-center gap-1.5 transition-colors opacity-0 group-hover:opacity-100"
        id="copy-btn"
      >
        <span class="material-symbols-outlined" style="font-size:14px" id="copy-icon">content_copy</span>
        <span id="copy-label">Copy</span>
      </button>
    </div>

    <div class="mt-3 flex items-center gap-2 text-xs text-slate-400">
      <span class="material-symbols-outlined text-red-400" style="font-size:14px">warning</span>
      Replace <span class="font-mono bg-red-50 text-red-600 px-1.5 rounded">YOUR_WIFI_NAME</span> and <span class="font-mono bg-red-50 text-red-600 px-1.5 rounded">YOUR_WIFI_PASSWORD</span> with your actual Wi-Fi credentials.
    </div>
  </div>
</div>

{{-- Step 2: Wiring --}}
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">2</div>
    <h2 class="font-semibold text-slate-800">Check your wiring</h2>
  </div>
  <div class="p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider border border-slate-200 rounded-tl-lg">Component</th>
            <th class="text-left px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider border border-slate-200">Pin</th>
            <th class="text-left px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider border border-slate-200 rounded-tr-lg">ESP32 GPIO</th>
          </tr>
        </thead>
        <tbody class="font-mono text-xs">
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2 border border-slate-200 font-sans font-medium text-slate-700">DHT22 (temp/humidity)</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">DATA</td>
            <td class="px-3 py-2 border border-slate-200 text-blue-600 font-semibold">GPIO 4 (D4)</td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2 border border-slate-200 font-sans font-medium text-slate-700">Door switch</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">SIG</td>
            <td class="px-3 py-2 border border-slate-200 text-blue-600 font-semibold">GPIO 15 (D15)</td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2 border border-slate-200 font-sans font-medium text-slate-700">Passive buzzer</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">+</td>
            <td class="px-3 py-2 border border-slate-200 text-blue-600 font-semibold">GPIO 2 (D2)</td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2 border border-slate-200 font-sans font-medium text-slate-700">OLED SSD1306</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">SDA / SCL</td>
            <td class="px-3 py-2 border border-slate-200 text-blue-600 font-semibold">GPIO 21 / GPIO 22</td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2 border border-slate-200 font-sans font-medium text-slate-700">Door switch</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">GND</td>
            <td class="px-3 py-2 border border-slate-200 text-slate-600">GND</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="mt-3 text-xs text-slate-400">VCC (3.3V) and GND for each component connect to the ESP32's 3V3 and GND pins via the breadboard power rails.</p>
  </div>
</div>

{{-- Step 3: Upload --}}
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">3</div>
    <h2 class="font-semibold text-slate-800">Upload to ESP32</h2>
  </div>
  <div class="p-5 space-y-3">
    <div class="flex items-start gap-3 text-sm text-slate-600">
      <span class="material-symbols-outlined text-blue-500 mt-0.5" style="font-size:18px">looks_one</span>
      <p>In Arduino IDE, select board: <strong class="text-slate-800">ESP32 Dev Module</strong> and your COM port.</p>
    </div>
    <div class="flex items-start gap-3 text-sm text-slate-600">
      <span class="material-symbols-outlined text-blue-500 mt-0.5" style="font-size:18px">looks_two</span>
      <p>Click <strong class="text-slate-800">Upload</strong>. When you see <code class="bg-slate-100 px-1 rounded text-xs">Connecting......</code> in the console, <strong class="text-slate-800">hold the BOOT button</strong> on the ESP32 until uploading begins.</p>
    </div>
    <div class="flex items-start gap-3 text-sm text-slate-600">
      <span class="material-symbols-outlined text-blue-500 mt-0.5" style="font-size:18px">looks_3</span>
      <p>After upload, open Serial Monitor at <strong class="text-slate-800">115200 baud</strong>. You should see <code class="bg-slate-100 px-1 rounded text-xs font-mono">WiFi connected</code> followed by Firebase data pushes every 5 seconds.</p>
    </div>
    <div class="mt-2 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 flex items-start gap-2">
      <span class="material-symbols-outlined text-base">tips_and_updates</span>
      <p>Once the ESP32 is online, your dashboard will start showing live data within 5–10 seconds. The Laravel scheduler stores readings to the database every minute.</p>
    </div>
  </div>
</div>

{{-- CTA buttons --}}
<div class="flex flex-col sm:flex-row gap-3">
  <a
    href="{{ route('setup.alerts') }}"
    class="flex-1 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
  >
    Done, continue
    <span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span>
  </a>
  <a
    href="{{ route('dashboard') }}"
    class="px-6 py-3.5 border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-1.5"
  >
    <span class="material-symbols-outlined" style="font-size:16px">skip_next</span>
    Skip to dashboard
  </a>
</div>

<script>
function copyConfig() {
  const pre = document.getElementById('config-block');
  const text = pre.innerText;
  navigator.clipboard.writeText(text).then(() => {
    document.getElementById('copy-icon').textContent  = 'check';
    document.getElementById('copy-label').textContent = 'Copied!';
    setTimeout(() => {
      document.getElementById('copy-icon').textContent  = 'content_copy';
      document.getElementById('copy-label').textContent = 'Copy';
    }, 2000);
  });
}
</script>

@endsection
