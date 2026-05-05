@extends('layouts.app')

@section('title', 'Equipment Inventory | DryBox AI')

@section('content')
<div class="max-w-7xl mx-auto space-y-md">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">Equipment Inventory</h1>
            <p class="font-body-base text-on-surface-variant mt-1">
                Live status of all connected dry storage units —
                <span id="unit-count" class="font-semibold text-on-surface">loading…</span>
            </p>
        </div>
        <div class="flex items-center gap-3 self-start sm:self-auto">
            {{-- Connection badge --}}
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-outline-variant rounded-xl text-sm">
                <div class="w-2 h-2 rounded-full bg-slate-300 animate-pulse" id="conn-dot"></div>
                <span class="font-medium text-slate-500" id="conn-label">Connecting…</span>
            </div>
        </div>
    </div>

    {{-- ── Summary Stats Bar ──────────────────────────────────── --}}
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4" id="stats-bar">
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">TEMPERATURE</span>
            <span class="font-data-num text-3xl text-on-background leading-none" id="stat-temp">--</span>
            <span class="text-xs text-slate-400">°C — current</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">HUMIDITY</span>
            <span class="font-data-num text-3xl text-on-background leading-none" id="stat-hum">--</span>
            <span class="text-xs text-slate-400">% relative</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">UNIT STATUS</span>
            <span class="font-headline-md text-xl font-bold leading-none mt-1" id="stat-status">--</span>
            <span class="text-xs text-slate-400">from sensor</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">LAST SEEN</span>
            <span class="font-title-sm text-on-surface leading-none mt-1" id="stat-time">--</span>
            <span class="text-xs text-slate-400">latest reading</span>
        </div>
    </section>

    {{-- ── Equipment Cards Grid ──────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="equipment-grid">
        {{-- Spinner while loading --}}
        <div class="col-span-full py-20 flex flex-col items-center justify-center text-slate-400" id="loading-state">
            <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
            <p class="font-headline-md">Fetching equipment from Firebase…</p>
        </div>
    </section>

    {{-- ── Detail Modal ──────────────────────────────────────── --}}
    <div id="detail-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="modal-backdrop"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden z-10">
            <div class="h-1 w-full" id="modal-top-bar"></div>
            <div class="p-8">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="font-display-lg text-2xl font-bold text-primary" id="modal-title">--</h2>
                        <p class="text-sm text-slate-500 mt-1" id="modal-subtitle">--</p>
                    </div>
                    <button id="modal-close" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-orange-500 text-base">thermostat</span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Temperature</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background" id="modal-temp">--<span class="text-lg">°C</span></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-blue-500 text-base">water_drop</span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
                        </div>
                        <p class="font-data-num text-3xl text-on-background" id="modal-hum">--<span class="text-lg">%</span></p>
                    </div>
                </div>

                <div class="space-y-3 mb-6">
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Sensor Status</span>
                        <span class="font-semibold text-sm" id="modal-status">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Firebase Path</span>
                        <span class="font-mono text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded">drybox/</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Last Reading</span>
                        <span class="font-semibold text-sm" id="modal-time">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-slate-500">Humidity Level</span>
                        <div class="w-40">
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-700" id="modal-bar" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <button id="modal-close-btn" class="w-full py-3 bg-primary text-white rounded-xl font-semibold hover:opacity-90 transition">
                    Close
                </button>
            </div>
        </div>
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

  // ── Thresholds (synced from Settings page via localStorage) ───
  const CRIT_THRESH = parseInt(localStorage.getItem('critThreshold') ?? 45);
  const WARN_THRESH = parseInt(localStorage.getItem('warnThreshold') ?? 35);

  // ── DOM Refs ───────────────────────────────────────────────
  const grid         = document.getElementById('equipment-grid');
  const loadingState = document.getElementById('loading-state');
  const connDot      = document.getElementById('conn-dot');
  const connLabel    = document.getElementById('conn-label');
  const unitCount    = document.getElementById('unit-count');
  const statTemp     = document.getElementById('stat-temp');
  const statHum      = document.getElementById('stat-hum');
  const statStatus   = document.getElementById('stat-status');
  const statTime     = document.getElementById('stat-time');

  // ── Modal Refs ─────────────────────────────────────────────
  const modal         = document.getElementById('detail-modal');
  const modalBackdrop = document.getElementById('modal-backdrop');
  const modalClose    = document.getElementById('modal-close');
  const modalCloseBtn = document.getElementById('modal-close-btn');
  const modalTopBar   = document.getElementById('modal-top-bar');
  const modalTitle    = document.getElementById('modal-title');
  const modalSubtitle = document.getElementById('modal-subtitle');
  const modalTemp     = document.getElementById('modal-temp');
  const modalHum      = document.getElementById('modal-hum');
  const modalStatus   = document.getElementById('modal-status');
  const modalTime     = document.getElementById('modal-time');
  const modalBar      = document.getElementById('modal-bar');

  let latestData = null; // store latest snapshot for modal

  // ── Status helpers ─────────────────────────────────────────
  function getStatusMeta(statusStr, humidity) {
    const s = (statusStr || '').toLowerCase();
    if (s.includes('critical') || humidity > CRIT_THRESH) {
      return { label: 'CRITICAL', badge: 'bg-red-100 text-red-700',  bar: 'bg-red-500',   border: 'border-red-400',  topBar: 'bg-red-500',   dot: 'bg-red-500',    text: 'text-red-600' };
    } else if (s.includes('warning') || humidity > WARN_THRESH) {
      return { label: 'WARNING',  badge: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500', border: 'border-amber-400', topBar: 'bg-amber-500', dot: 'bg-amber-500',  text: 'text-amber-600' };
    }
    return { label: 'SAFE',     badge: 'bg-emerald-100 text-emerald-700', bar: 'bg-blue-500', border: 'border-emerald-400', topBar: 'bg-emerald-500', dot: 'bg-emerald-500', text: 'text-emerald-600' };
  }

  // ── Connection state ───────────────────────────────────────
  db.ref('.info/connected').on('value', snap => {
    if (snap.val()) {
      connDot.className   = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
      connLabel.textContent = 'Live';
      connLabel.className = 'font-medium text-emerald-600';
    } else {
      connDot.className   = 'w-2 h-2 rounded-full bg-amber-400 animate-pulse';
      connLabel.textContent = 'Reconnecting…';
      connLabel.className = 'font-medium text-amber-500';
    }
  });

  // ── Render equipment card ──────────────────────────────────
  function renderCard(data) {
    const hum    = parseFloat(data.humidity    ?? 0);
    const temp   = parseFloat(data.temperature ?? 0);
    const status = data.status || 'Unknown';
    const now    = new Date().toLocaleTimeString();
    const meta   = getStatusMeta(status, hum);

    return `
      <div class="bg-white border border-outline-variant rounded-xl overflow-hidden hover:shadow-lg transition-all group cursor-pointer equipment-card">
        {{-- Colour-coded top strip --}}
        <div class="h-1 ${meta.topBar}"></div>

        <div class="p-6">
          {{-- Header row --}}
          <div class="flex justify-between items-start mb-4">
            <span class="text-xs font-bold px-2.5 py-1 rounded-full ${meta.badge} flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full ${meta.dot} animate-pulse"></span>
              ${meta.label}
            </span>
            <span class="material-symbols-outlined text-slate-300 group-hover:text-primary transition-colors">sensors</span>
          </div>

          {{-- Unit ID + label --}}
          <h3 class="font-display-lg text-xl font-bold text-primary mb-1">DRYBOX</h3>
          <p class="text-sm text-slate-500 mb-5">Firebase path: <code class="bg-slate-100 px-1 rounded text-xs">drybox/</code></p>

          {{-- Live readings --}}
          <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="material-symbols-outlined text-orange-400" style="font-size:14px">thermostat</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temp</span>
              </div>
              <p class="font-data-num text-2xl text-on-background leading-none">${temp.toFixed(1)}<span class="text-sm text-slate-400">°C</span></p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="material-symbols-outlined text-blue-400" style="font-size:14px">water_drop</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
              </div>
              <p class="font-data-num text-2xl text-on-background leading-none">${hum.toFixed(1)}<span class="text-sm text-slate-400">%</span></p>
            </div>
          </div>

          {{-- Humidity bar --}}
          <div class="mb-5">
            <div class="flex justify-between text-xs text-slate-400 mb-1.5">
              <span>Humidity level</span>
              <span>${hum.toFixed(1)}%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full ${meta.bar} rounded-full transition-all duration-700" style="width:${Math.min(hum,100)}%"></div>
            </div>
          </div>

          {{-- Footer info --}}
          <div class="border-t border-slate-100 pt-4 flex justify-between items-center text-xs text-slate-400">
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined" style="font-size:13px">schedule</span>
              ${now}
            </span>
            <span class="${meta.text} font-semibold">${status}</span>
          </div>
        </div>

        {{-- View Details button --}}
        <button class="w-full py-3 border-t border-slate-100 text-primary font-bold text-sm hover:bg-primary/5 transition-colors view-details-btn">
          View Details
        </button>
      </div>
    `;
  }

  // ── Open Modal ─────────────────────────────────────────────
  function openModal(data) {
    const hum  = parseFloat(data.humidity    ?? 0);
    const temp = parseFloat(data.temperature ?? 0);
    const meta = getStatusMeta(data.status, hum);

    modalTopBar.className  = `h-1 w-full ${meta.topBar}`;
    modalTitle.textContent = 'DRYBOX Unit';
    modalSubtitle.textContent = `Firebase path: drybox/ — Status: ${meta.label}`;
    modalTemp.innerHTML    = `${temp.toFixed(1)}<span class="text-lg text-slate-400">°C</span>`;
    modalHum.innerHTML     = `${hum.toFixed(1)}<span class="text-lg text-slate-400">%</span>`;
    modalStatus.textContent = data.status || 'Unknown';
    modalStatus.className  = `font-semibold text-sm ${meta.text}`;
    modalTime.textContent  = new Date().toLocaleTimeString();
    modalBar.style.width   = Math.min(hum, 100) + '%';
    modalBar.className     = `h-full rounded-full transition-all duration-700 ${meta.bar}`;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  modalBackdrop.addEventListener('click', closeModal);
  modalClose.addEventListener('click', closeModal);
  modalCloseBtn.addEventListener('click', closeModal);

  // ── Main Firebase Listener ─────────────────────────────────
  db.ref("drybox").on("value", snapshot => {
    const data = snapshot.val();
    if (!data) {
      loadingState.innerHTML = `<p class="text-slate-400">No data found at <code>drybox/</code> in Firebase.</p>`;
      return;
    }

    latestData = data;
    const hum    = parseFloat(data.humidity    ?? 0);
    const temp   = parseFloat(data.temperature ?? 0);
    const status = data.status || 'Unknown';
    const now    = new Date().toLocaleTimeString();
    const meta   = getStatusMeta(status, hum);

    // Update summary stats bar
    statTemp.textContent   = temp.toFixed(1);
    statHum.textContent    = hum.toFixed(1);
    statTime.textContent   = now;
    statStatus.textContent = meta.label;
    statStatus.className   = `font-headline-md text-xl font-bold leading-none mt-1 ${meta.text}`;
    unitCount.textContent  = '1 unit connected';

    // Render card grid
    grid.innerHTML = renderCard(data) + `
      <div class="border-2 border-dashed border-outline-variant rounded-xl flex flex-col items-center justify-center p-8 text-slate-400 hover:bg-slate-50 transition-colors cursor-pointer group">
        <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
          <span class="material-symbols-outlined text-3xl">add</span>
        </div>
        <span class="font-semibold text-sm">Add New Unit</span>
        <p class="text-xs mt-1 text-center">Register another IoT sensor</p>
      </div>
    `;

    // Attach click handler to the "View Details" button
    document.querySelector('.view-details-btn')?.addEventListener('click', () => openModal(latestData));
    document.querySelector('.equipment-card')?.addEventListener('click', (e) => {
      if (!e.target.closest('.view-details-btn')) openModal(latestData);
    });

    // Update modal if open
    if (!modal.classList.contains('hidden')) openModal(data);
  });
</script>
@endsection
