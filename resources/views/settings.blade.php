@extends('layouts.app')

@section('title', 'Settings | DryBox AI')

@section('content')
<div class="max-w-7xl mx-auto pb-28">

    <header class="mb-10">
        <h1 class="font-display-lg text-display-lg text-blue-900 mb-2">System Configuration</h1>
        <p class="font-body-base text-on-surface-variant">Manage thresholds, Firebase connectivity, and your account.</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">

        {{-- ── Left Column ────────────────────────────────────── --}}
        <div class="md:col-span-8 space-y-8">

            {{-- Threshold Sliders --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary">notification_important</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Alert Thresholds</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <div class="space-y-3">
                        <label class="block font-label-caps text-label-caps text-on-surface-variant">CRITICAL HUMIDITY THRESHOLD</label>
                        <div class="flex items-center gap-4">
                            <input id="crit-slider" class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-red-500"
                                   type="range" min="20" max="80" value="45">
                            <span id="crit-val" class="font-data-num text-headline-md text-red-500 min-w-[56px]">45%</span>
                        </div>
                        <p class="text-body-sm text-outline">Triggers Critical alert when humidity exceeds this value.</p>
                    </div>
                    <div class="space-y-3">
                        <label class="block font-label-caps text-label-caps text-on-surface-variant">WARNING HUMIDITY THRESHOLD</label>
                        <div class="flex items-center gap-4">
                            <input id="warn-slider" class="w-full h-2 bg-surface-container rounded-lg appearance-none cursor-pointer accent-amber-500"
                                   type="range" min="10" max="60" value="35">
                            <span id="warn-val" class="font-data-num text-headline-md text-amber-500 min-w-[56px]">35%</span>
                        </div>
                        <p class="text-body-sm text-outline">Triggers Warning alert when humidity exceeds this value.</p>
                    </div>
                </div>

                {{-- Live preview band --}}
                <div class="mt-8 p-4 bg-slate-50 rounded-xl border border-outline-variant">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Threshold Preview</p>
                    <div class="relative w-full h-6 bg-gradient-to-r from-emerald-200 via-amber-200 to-red-200 rounded-full overflow-hidden">
                        <div id="warn-marker" class="absolute top-0 h-full w-0.5 bg-amber-600" style="left:35%">
                            <span class="absolute -top-5 -translate-x-1/2 text-[10px] font-bold text-amber-600" id="warn-label">35%</span>
                        </div>
                        <div id="crit-marker" class="absolute top-0 h-full w-0.5 bg-red-600" style="left:45%">
                            <span class="absolute -top-5 -translate-x-1/2 text-[10px] font-bold text-red-600" id="crit-label">45%</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1.5">
                        <span>0%</span><span>50%</span><span>100%</span>
                    </div>
                </div>
            </section>

            {{-- Firebase Connection --}}
            <section class="bg-white border border-outline-variant rounded-xl p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-primary/5 rounded-full -mr-20 -mt-20 pointer-events-none"></div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary">cloud</span>
                    <h2 class="font-headline-md text-headline-md text-on-surface">Firebase Connection</h2>
                </div>

                <div class="space-y-5">
                    {{-- Database URL (read-only from config) --}}
                    <div>
                        <label class="block font-label-caps text-label-caps text-on-surface-variant mb-2">DATABASE URL</label>
                        <div class="flex items-center gap-2 px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl font-mono text-sm text-slate-600 break-all">
                            <span class="material-symbols-outlined text-slate-400 flex-shrink-0" style="font-size:16px">link</span>
                            {{ config('firebase.database_url') }}
                        </div>
                    </div>

                    {{-- API Key --}}
                    <div>
                        <label class="block font-label-caps text-label-caps text-on-surface-variant mb-2">API KEY</label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input id="api-key-field" type="password"
                                       value="{{ config('firebase.api_key') }}"
                                       readonly
                                       class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl font-mono text-sm text-slate-700 pr-10">
                                <button onclick="toggleKey()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined" id="key-eye-icon" style="font-size:18px">visibility</span>
                                </button>
                            </div>
                            <button onclick="testConnection()" id="test-btn"
                                    class="px-5 py-3 bg-primary text-white rounded-xl font-semibold text-sm hover:opacity-90 transition flex items-center gap-2 whitespace-nowrap">
                                <span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Connection
                            </button>
                        </div>
                    </div>

                    {{-- Connection status card --}}
                    <div id="conn-result" class="hidden p-4 rounded-xl border flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 flex-shrink-0" id="conn-icon">check_circle</span>
                        <div>
                            <p class="font-semibold text-sm" id="conn-title">--</p>
                            <p class="text-xs mt-0.5" id="conn-desc">--</p>
                        </div>
                    </div>

                    {{-- Live indicator --}}
                    <div class="flex items-center gap-3 p-4 bg-surface-container-low rounded-xl border border-outline-variant">
                        <div class="w-3 h-3 rounded-full bg-slate-300 animate-pulse" id="fb-dot"></div>
                        <div>
                            <p class="text-sm font-semibold text-on-surface" id="fb-status-text">Checking…</p>
                            <p class="text-xs text-on-surface-variant">Firebase Realtime Database — drybox/</p>
                        </div>
                        <span class="ml-auto text-xs font-mono text-slate-400" id="fb-last-read">--</span>
                    </div>
                </div>
            </section>

        </div>

        {{-- ── Right Column ────────────────────────────────────── --}}
        <div class="md:col-span-4 space-y-8">

            {{-- User Profile (dynamic from Auth) --}}
            <section class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="h-24 bg-gradient-to-r from-primary to-secondary relative"></div>
                <div class="px-6 pb-6 pt-10 relative">
                    <div class="absolute -top-10 left-6 h-20 w-20 rounded-xl border-4 border-white bg-primary flex items-center justify-center shadow-md">
                        <span class="text-white font-display font-bold text-3xl">{{ substr(Auth::user()->name, 0, 1) }}</span>
                    </div>
                    <div class="mb-4">
                        <h3 class="font-headline-md text-on-surface">{{ Auth::user()->name }}</h3>
                        <p class="text-body-sm text-on-surface-variant">{{ Auth::user()->email }}</p>
                        <span class="mt-2 inline-block text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                            System Administrator
                        </span>
                    </div>
                    <div class="space-y-2 pt-4 border-t border-slate-100 text-sm text-slate-500">
                        <div class="flex justify-between">
                            <span>Member since</span>
                            <span class="font-semibold text-on-surface">{{ Auth::user()->created_at->format('M Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Account ID</span>
                            <span class="font-mono text-xs">#{{ Auth::user()->id }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full py-2.5 border border-error text-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">logout</span> Sign Out
                        </button>
                    </form>
                </div>
            </section>

            {{-- Live sensor snapshot --}}
            <section class="bg-white border border-outline-variant rounded-xl p-6">
                <h3 class="font-title-sm text-title-sm text-on-surface mb-4">Live Sensor Snapshot</h3>
                <div class="space-y-3" id="settings-snapshot">
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="material-symbols-outlined text-orange-400" style="font-size:16px">thermostat</span>Temperature
                        </span>
                        <span class="font-semibold text-sm" id="snap-temp">--</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-400" style="font-size:16px">water_drop</span>Humidity
                        </span>
                        <span class="font-semibold text-sm" id="snap-hum">--</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-slate-500 flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-400" style="font-size:16px">sensors</span>Status
                        </span>
                        <span class="font-semibold text-sm" id="snap-status">--</span>
                    </div>
                </div>
            </section>

            {{-- Danger Zone --}}
            <section class="bg-error-container/10 border border-error/20 rounded-xl p-6">
                <h2 class="font-title-sm text-title-sm text-error mb-2">Danger Zone</h2>
                <p class="text-body-sm text-on-error-container/70 mb-4">Permanent actions that cannot be reversed.</p>
                <button onclick="if(confirm('Are you sure? This cannot be undone.'))" class="w-full py-2.5 text-error border border-error rounded-lg font-semibold text-sm hover:bg-error hover:text-white transition-all">
                    Factory Reset Hub
                </button>
            </section>

        </div>
    </div>
</div>

{{-- Sticky Save Bar (for threshold changes) --}}
<div id="save-bar" class="fixed bottom-0 left-0 lg:left-64 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 px-6 py-4 flex items-center justify-between gap-4 z-40 translate-y-full transition-transform duration-300">
    <p class="text-sm text-slate-500 flex items-center gap-2">
        <span class="material-symbols-outlined text-amber-500 text-base">edit</span>
        You have unsaved threshold changes
    </p>
    <div class="flex items-center gap-3">
        <button onclick="resetThresholds()" class="px-5 py-2 text-slate-500 font-semibold text-sm hover:text-on-surface transition-colors">Cancel</button>
        <button onclick="saveThresholds()" class="px-7 py-2 bg-primary text-white rounded-lg font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">save</span> Save Changes
        </button>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>
<script>
  // ── Firebase ───────────────────────────────────────────────
  const firebaseConfig = {
    apiKey:      "{{ config('firebase.api_key') }}",
    databaseURL: "{{ config('firebase.database_url') }}",
  };
  if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
  const db = firebase.database();

  // ── Threshold sliders ──────────────────────────────────────
  const critSlider = document.getElementById('crit-slider');
  const warnSlider = document.getElementById('warn-slider');
  const critVal    = document.getElementById('crit-val');
  const warnVal    = document.getElementById('warn-val');
  const saveBar    = document.getElementById('save-bar');
  let dirty = false;

  // ── Load saved thresholds from localStorage ────────────────
  const savedCrit = localStorage.getItem('critThreshold');
  const savedWarn = localStorage.getItem('warnThreshold');
  if (savedCrit) critSlider.value = savedCrit;
  if (savedWarn) warnSlider.value = savedWarn;
  updateMarkers(); // reflect loaded values in labels
  dirty = false;   // reset dirty flag after init load

  function updateMarkers() {
    const c = critSlider.value, w = warnSlider.value;
    critVal.textContent = c + '%';
    warnVal.textContent = w + '%';
    document.getElementById('crit-marker').style.left = c + '%';
    document.getElementById('warn-marker').style.left = w + '%';
    document.getElementById('crit-label').textContent = c + '%';
    document.getElementById('warn-label').textContent = w + '%';
    if (!dirty) { dirty = true; saveBar.style.transform = 'translateY(0)'; }
  }

  critSlider.addEventListener('input', updateMarkers);
  warnSlider.addEventListener('input', updateMarkers);

  window.resetThresholds = function() {
    critSlider.value = 45; warnSlider.value = 35;
    updateMarkers(); dirty = false;
    saveBar.style.transform = 'translateY(100%)';
  };

  window.saveThresholds = function() {
    // Persist to localStorage so all pages pick up the new values
    localStorage.setItem('critThreshold', critSlider.value);
    localStorage.setItem('warnThreshold', warnSlider.value);
    dirty = false;
    saveBar.style.transform = 'translateY(100%)';
    showToast('Thresholds saved — Critical: ' + critSlider.value + '%, Warning: ' + warnSlider.value + '%');
  };

  // ── Show/hide API key ──────────────────────────────────────
  window.toggleKey = function() {
    const field = document.getElementById('api-key-field');
    const icon  = document.getElementById('key-eye-icon');
    field.type  = field.type === 'password' ? 'text' : 'password';
    icon.textContent = field.type === 'password' ? 'visibility' : 'visibility_off';
  };

  // ── Test connection ────────────────────────────────────────
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
      document.getElementById('conn-desc').textContent = ok
        ? 'Firebase Realtime Database is reachable and responding.'
        : 'Could not reach Firebase. Check your API key and database URL.';
      document.getElementById('conn-desc').className   = `text-xs mt-0.5 ${ok ? 'text-emerald-600' : 'text-red-600'}`;
    }).catch(() => {
      btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Connection';
      btn.disabled = false;
    });
  };

  // ── Toast notification ─────────────────────────────────────
  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'fixed bottom-24 right-6 z-50 bg-on-surface text-surface px-5 py-3 rounded-xl shadow-xl text-sm font-semibold flex items-center gap-2 transition-all';
    t.style.cssText = 'background:#1e293b;color:white;';
    t.innerHTML = `<span class="material-symbols-outlined text-emerald-400" style="font-size:18px">check_circle</span> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  // ── Firebase live data (snapshot + connection indicator) ───
  db.ref('.info/connected').on('value', snap => {
    const live = snap.val() === true;
    document.getElementById('fb-dot').className = `w-3 h-3 rounded-full ${live ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400 animate-pulse'}`;
    document.getElementById('fb-status-text').textContent = live ? 'Connected — Receiving live data' : 'Reconnecting to Firebase…';
  });

  db.ref("drybox").on("value", snapshot => {
    const data = snapshot.val();
    if (!data) return;
    const now  = new Date().toLocaleTimeString();
    const hum  = parseFloat(data.humidity    ?? 0).toFixed(1);
    const temp = parseFloat(data.temperature ?? 0).toFixed(1);
    document.getElementById('snap-hum').textContent    = hum + ' %';
    document.getElementById('snap-temp').textContent   = temp + ' °C';
    document.getElementById('snap-status').textContent = data.status || 'Unknown';
    document.getElementById('fb-last-read').textContent = now;
  });
</script>
@endsection
