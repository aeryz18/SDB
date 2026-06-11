@extends('layouts.demo')

@section('title', 'Equipment Inventory — Demo | DryBox AI')

@section('content')
<div class="max-w-7xl mx-auto space-y-md">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">Equipment Inventory</h1>
            <p class="font-body-base text-on-surface-variant mt-1">
                Live status of all connected dry storage units —
                <span id="unit-count" class="font-semibold text-on-surface">1 unit connected</span>
            </p>
        </div>
        <div class="flex items-center gap-3 self-start sm:self-auto">
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-outline-variant rounded-xl text-sm">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" id="conn-dot"></div>
                <span class="font-medium text-emerald-600" id="conn-label">Simulated</span>
            </div>
        </div>
    </div>

    {{-- ── Summary Stats Bar ──────────────────────────────────── --}}
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4" id="tour-stats-bar">
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
            <span class="font-headline-md text-xl font-bold leading-none mt-1 text-emerald-600" id="stat-status">SAFE</span>
            <span class="text-xs text-slate-400">from sensor</span>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4 flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-on-surface-variant">LAST SEEN</span>
            <span class="font-title-sm text-on-surface leading-none mt-1" id="stat-time">--</span>
            <span class="text-xs text-slate-400">latest reading</span>
        </div>
    </section>

    {{-- ── Equipment Cards Grid ──────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="tour-equipment-grid"></section>

    {{-- ── Detail Modal ──────────────────────────────────────── --}}
    <div id="detail-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="modal-backdrop"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden z-10">
            <div class="h-1 w-full bg-emerald-500" id="modal-top-bar"></div>
            <div class="p-8">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="font-display-lg text-2xl font-bold text-primary" id="modal-title">DRYBOX Unit</h2>
                        <p class="text-sm text-slate-500 mt-1" id="modal-subtitle">Demo — Simulated Sensor</p>
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
                        <span class="font-semibold text-sm text-emerald-600" id="modal-status">Safe</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Firebase Path</span>
                        <span class="font-mono text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded">demo/drybox/</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Last Reading</span>
                        <span class="font-semibold text-sm" id="modal-time">--</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-slate-500">Humidity Level</span>
                        <div class="w-40">
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all duration-700" id="modal-bar" style="width:0%"></div>
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
<script>
  // ── DEMO: Simulated Equipment ───────────────────────────────
  const CRIT_THRESH = parseInt(localStorage.getItem('critThreshold') ?? 45);
  const WARN_THRESH = parseInt(localStorage.getItem('warnThreshold') ?? 35);

  const grid       = document.getElementById('tour-equipment-grid');
  const statTemp   = document.getElementById('stat-temp');
  const statHum    = document.getElementById('stat-hum');
  const statStatus = document.getElementById('stat-status');
  const statTime   = document.getElementById('stat-time');

  // Modal refs
  const modal         = document.getElementById('detail-modal');
  const modalBackdrop = document.getElementById('modal-backdrop');
  const modalClose    = document.getElementById('modal-close');
  const modalCloseBtn = document.getElementById('modal-close-btn');
  const modalTopBar   = document.getElementById('modal-top-bar');
  const modalTemp     = document.getElementById('modal-temp');
  const modalHum      = document.getElementById('modal-hum');
  const modalStatus   = document.getElementById('modal-status');
  const modalTime     = document.getElementById('modal-time');
  const modalBar      = document.getElementById('modal-bar');

  let simTemp = 26.2, simHum = 30.5;

  function getStatusMeta(humidity) {
    if (humidity > CRIT_THRESH)
      return { label: 'CRITICAL', badge: 'bg-red-100 text-red-700', bar: 'bg-red-500', topBar: 'bg-red-500', dot: 'bg-red-500', text: 'text-red-600' };
    if (humidity > WARN_THRESH)
      return { label: 'WARNING', badge: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500', topBar: 'bg-amber-500', dot: 'bg-amber-500', text: 'text-amber-600' };
    return { label: 'SAFE', badge: 'bg-emerald-100 text-emerald-700', bar: 'bg-blue-500', topBar: 'bg-emerald-500', dot: 'bg-emerald-500', text: 'text-emerald-600' };
  }

  function renderEquipment() {
    simTemp += (Math.random() - 0.5) * 0.4;
    simHum  += (Math.random() - 0.45) * 0.8;
    simTemp  = Math.max(23, Math.min(34, simTemp));
    simHum   = Math.max(24, Math.min(50, simHum));

    const now  = new Date().toLocaleTimeString();
    const meta = getStatusMeta(simHum);

    statTemp.textContent   = simTemp.toFixed(1);
    statHum.textContent    = simHum.toFixed(1);
    statTime.textContent   = now;
    statStatus.textContent = meta.label;
    statStatus.className   = `font-headline-md text-xl font-bold leading-none mt-1 ${meta.text}`;

    grid.innerHTML = `
      <div class="bg-white border border-outline-variant rounded-xl overflow-hidden hover:shadow-lg transition-all group cursor-pointer equipment-card">
        <div class="h-1 ${meta.topBar}"></div>
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <span class="text-xs font-bold px-2.5 py-1 rounded-full ${meta.badge} flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full ${meta.dot} animate-pulse"></span>
              ${meta.label}
            </span>
            <span class="material-symbols-outlined text-slate-300 group-hover:text-primary transition-colors">sensors</span>
          </div>
          <h3 class="font-display-lg text-xl font-bold text-primary mb-1">DRYBOX</h3>
          <p class="text-sm text-slate-500 mb-5">Firebase path: <code class="bg-slate-100 px-1 rounded text-xs">demo/drybox/</code></p>
          <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="material-symbols-outlined text-orange-400" style="font-size:14px">thermostat</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temp</span>
              </div>
              <p class="font-data-num text-2xl text-on-background leading-none">${simTemp.toFixed(1)}<span class="text-sm text-slate-400">°C</span></p>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="material-symbols-outlined text-blue-400" style="font-size:14px">water_drop</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Humidity</span>
              </div>
              <p class="font-data-num text-2xl text-on-background leading-none">${simHum.toFixed(1)}<span class="text-sm text-slate-400">%</span></p>
            </div>
          </div>
          <div class="mb-5">
            <div class="flex justify-between text-xs text-slate-400 mb-1.5">
              <span>Humidity level</span>
              <span>${simHum.toFixed(1)}%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full ${meta.bar} rounded-full transition-all duration-700" style="width:${Math.min(simHum,100)}%"></div>
            </div>
          </div>
          <div class="border-t border-slate-100 pt-4 flex justify-between items-center text-xs text-slate-400">
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined" style="font-size:13px">schedule</span>
              ${now}
            </span>
            <span class="${meta.text} font-semibold">${meta.label}</span>
          </div>
        </div>
        <button class="w-full py-3 border-t border-slate-100 text-primary font-bold text-sm hover:bg-primary/5 transition-colors view-details-btn">
          View Details
        </button>
      </div>

      <div class="border-2 border-dashed border-outline-variant rounded-xl flex flex-col items-center justify-center p-8 text-slate-400 hover:bg-slate-50 transition-colors cursor-pointer group">
        <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
          <span class="material-symbols-outlined text-3xl">add</span>
        </div>
        <span class="font-semibold text-sm">Add New Unit</span>
        <p class="text-xs mt-1 text-center">Register another IoT sensor</p>
      </div>
    `;

    // Attach events
    document.querySelector('.view-details-btn')?.addEventListener('click', openModal);
    document.querySelector('.equipment-card')?.addEventListener('click', (e) => {
      if (!e.target.closest('.view-details-btn')) openModal();
    });

    // Update modal if open
    if (!modal.classList.contains('hidden')) openModal();
  }

  function openModal() {
    const meta = getStatusMeta(simHum);
    modalTopBar.className  = `h-1 w-full ${meta.topBar}`;
    modalTemp.innerHTML    = `${simTemp.toFixed(1)}<span class="text-lg text-slate-400">°C</span>`;
    modalHum.innerHTML     = `${simHum.toFixed(1)}<span class="text-lg text-slate-400">%</span>`;
    modalStatus.textContent = meta.label;
    modalStatus.className  = `font-semibold text-sm ${meta.text}`;
    modalTime.textContent  = new Date().toLocaleTimeString();
    modalBar.style.width   = Math.min(simHum, 100) + '%';
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

  // Initial render + interval
  renderEquipment();
  setInterval(renderEquipment, 3000);
</script>
@endsection
