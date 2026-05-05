@extends('layouts.app')

@section('title', 'DryBox AI - Dashboard')

@section('content')
<div class="max-w-7xl mx-auto space-y-md">

    {{-- ── Page Header ────────────────────────────────────────────── --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary">System Dashboard</h1>
            <p class="font-body-base text-body-base text-on-surface-variant">
                Live monitoring — Smart Dry Box Sensor
            </p>
        </div>

        {{-- Connection Status Badge --}}
        <div class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-outline-variant rounded-xl self-start">
            <div class="w-2.5 h-2.5 rounded-full bg-slate-300 animate-pulse" id="live-dot"></div>
            <span class="text-sm font-semibold text-slate-500" id="connection-status">Connecting…</span>
        </div>
    </div>

    {{-- ── Dynamic Alert Banner (hidden until data arrives) ──────── --}}
    <section id="alert-banner" class="hidden" aria-live="polite"></section>

    {{-- ── Live Sensor Stat Cards ─────────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-grid-gutter">

        {{-- Temperature --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">TEMPERATURE</span>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="font-data-num text-data-num text-on-background leading-none transition-all" id="temp-value">--</span>
                        <span class="text-2xl font-semibold text-outline mb-1">°C</span>
                    </div>
                </div>
                <div class="p-3 bg-orange-50 rounded-xl group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-orange-500">thermostat</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center gap-1.5 text-xs text-on-surface-variant">
                <span class="material-symbols-outlined text-xs" style="font-size:14px">schedule</span>
                Updated: <span id="temp-time">--</span>
            </div>
        </div>

        {{-- Humidity --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">HUMIDITY</span>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="font-data-num text-data-num text-on-background leading-none transition-all" id="hum-value">--</span>
                        <span class="text-2xl font-semibold text-outline mb-1">%</span>
                    </div>
                </div>
                <div class="p-3 bg-blue-50 rounded-xl group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-blue-500">water_drop</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100">
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 transition-all duration-1000 rounded-full" id="hum-bar" style="width:0%"></div>
                </div>
                <p class="text-xs text-on-surface-variant mt-1">Relative Humidity Level</p>
            </div>
        </div>

        {{-- Status --}}
        <div class="bg-white border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="font-label-caps text-label-caps text-on-surface-variant block">STATUS</span>
                    <div class="mt-2">
                        <span class="font-headline-md text-headline-md text-on-surface" id="status-value">--</span>
                    </div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl group-hover:scale-110 transition-transform" id="status-icon-wrap">
                    <span class="material-symbols-outlined text-slate-400" id="status-icon">sensors</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-slate-300" id="status-dot"></div>
                <span class="text-xs text-on-surface-variant" id="status-sub">Waiting for data…</span>
            </div>
        </div>

    </section>

    {{-- ── Real-Time Humidity Chart ─────────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-md">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Live Humidity Trend</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Real-time readings from Firebase (last 20 points)</p>
            </div>
            <span class="flex items-center gap-2 text-xs text-emerald-600 font-bold uppercase tracking-wider bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                Live
            </span>
        </div>
        <div class="relative h-64">
            <canvas id="humidity-chart"></canvas>
        </div>
    </section>

    {{-- ── Connection Footer ────────────────────────────────────── --}}
    <section class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-surface-container-low border border-outline-variant rounded-xl p-md gap-4">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary">cloud_sync</span>
            <div>
                <span class="font-label-caps text-label-caps text-on-surface-variant block">FIREBASE REALTIME DATABASE</span>
                <span class="font-body-sm text-body-sm text-on-surface font-mono text-xs">iot-project-1d0fa-default-rtdb.firebaseio.com</span>
            </div>
        </div>
        <div class="text-left sm:text-right">
            <span class="font-label-caps text-label-caps text-on-surface-variant block">LAST READING</span>
            <span class="font-body-sm text-body-sm text-on-surface font-semibold" id="last-update-time">--</span>
        </div>
    </section>

</div>
@endsection

@section('scripts')
{{-- Firebase 9 compat + Chart.js --}}
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
  // ── Firebase Init ──────────────────────────────────────────────
  const firebaseConfig = {
    apiKey:      "{{ config('firebase.api_key') }}",
    databaseURL: "{{ config('firebase.database_url') }}",
  };
  firebase.initializeApp(firebaseConfig);
  const db = firebase.database();

  // ── Thresholds (synced from Settings page via localStorage) ───
  const CRIT_THRESH = parseInt(localStorage.getItem('critThreshold') ?? 45);
  const WARN_THRESH = parseInt(localStorage.getItem('warnThreshold') ?? 35);

  // ── DOM refs ───────────────────────────────────────────────────
  const tempEl        = document.getElementById('temp-value');
  const tempTimeEl    = document.getElementById('temp-time');
  const humEl         = document.getElementById('hum-value');
  const humBar        = document.getElementById('hum-bar');
  const statusEl      = document.getElementById('status-value');
  const statusDot     = document.getElementById('status-dot');
  const statusSub     = document.getElementById('status-sub');
  const statusIcon    = document.getElementById('status-icon');
  const statusIconWrap= document.getElementById('status-icon-wrap');
  const lastUpdateEl  = document.getElementById('last-update-time');
  const connStatusEl  = document.getElementById('connection-status');
  const liveDot       = document.getElementById('live-dot');
  const alertBanner   = document.getElementById('alert-banner');

  // ── Chart.js Setup ─────────────────────────────────────────────
  const ctx = document.getElementById('humidity-chart').getContext('2d');
  const humidityChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'Humidity (%)',
        data: [],
        borderColor: '#0061a4',
        backgroundColor: 'rgba(0,97,164,0.07)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#0061a4',
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 2.5,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: { label: ctx => ` ${ctx.parsed.y.toFixed(1)} %` }
        }
      },
      scales: {
        y: {
          min: 0, max: 100,
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { callback: v => v + '%', font: { family: 'Inter', size: 11 } }
        },
        x: {
          grid: { display: false },
          ticks: { font: { family: 'Inter', size: 10 }, maxTicksLimit: 8 }
        }
      },
      animation: { duration: 400 }
    }
  });

  // ── Status Helper ──────────────────────────────────────────────
  function applyStatus(statusStr, humidity) {
    const s = (statusStr || '').toLowerCase();
    const isCritical = s.includes('critical') || humidity > CRIT_THRESH;
    const isWarning  = s.includes('warning')  || (humidity > WARN_THRESH && !isCritical);

    statusEl.textContent = statusStr || 'Unknown';

    if (isCritical) {
      statusEl.className      = 'font-headline-md text-headline-md text-red-600';
      statusDot.className     = 'w-2 h-2 rounded-full bg-red-500 animate-pulse';
      statusIcon.textContent  = 'crisis_alert';
      statusIconWrap.className= 'p-3 bg-red-50 rounded-xl';
      statusIcon.className    = 'material-symbols-outlined text-red-500';
      statusSub.textContent   = 'Critical — immediate action required!';
      humBar.className        = 'h-full bg-red-500 transition-all duration-1000 rounded-full';
      showAlert('CRITICAL', true, `Humidity at ${humidity}% — desiccant may be saturated!`);

    } else if (isWarning) {
      statusEl.className      = 'font-headline-md text-headline-md text-amber-600';
      statusDot.className     = 'w-2 h-2 rounded-full bg-amber-500 animate-pulse';
      statusIcon.textContent  = 'warning';
      statusIconWrap.className= 'p-3 bg-amber-50 rounded-xl';
      statusIcon.className    = 'material-symbols-outlined text-amber-500';
      statusSub.textContent   = 'Humidity approaching critical threshold';
      humBar.className        = 'h-full bg-amber-500 transition-all duration-1000 rounded-full';
      showAlert('WARNING', false, `Humidity at ${humidity}% — approaching critical threshold.`);

    } else {
      statusEl.className      = 'font-headline-md text-headline-md text-emerald-600';
      statusDot.className     = 'w-2 h-2 rounded-full bg-emerald-500';
      statusIcon.textContent  = 'check_circle';
      statusIconWrap.className= 'p-3 bg-emerald-50 rounded-xl';
      statusIcon.className    = 'material-symbols-outlined text-emerald-500';
      statusSub.textContent   = 'All conditions within safe limits';
      humBar.className        = 'h-full bg-blue-500 transition-all duration-1000 rounded-full';
      alertBanner.classList.add('hidden');
    }
  }

  function showAlert(level, isCritical, msg) {
    const bg     = isCritical ? 'bg-red-50 border-red-200'     : 'bg-amber-50 border-amber-200';
    const iconBg = isCritical ? 'bg-red-500'                   : 'bg-amber-500';
    const icon   = isCritical ? 'crisis_alert'                 : 'warning';
    const text   = isCritical ? 'text-red-800'                 : 'text-amber-800';
    const sub    = isCritical ? 'text-red-600'                 : 'text-amber-600';
    alertBanner.classList.remove('hidden');
    alertBanner.innerHTML = `
      <div class="${bg} border rounded-xl p-md flex items-start gap-4">
        <div class="p-3 ${iconBg} rounded-full text-white flex-shrink-0">
          <span class="material-symbols-outlined">${icon}</span>
        </div>
        <div>
          <h3 class="font-headline-md ${text}">${level}: Drybox Sensor</h3>
          <p class="font-body-sm ${sub} mt-1">${msg}</p>
        </div>
      </div>`;
  }

  // ── Firebase Connection State ──────────────────────────────────
  db.ref('.info/connected').on('value', snap => {
    if (snap.val() === true) {
      connStatusEl.textContent  = 'Live';
      connStatusEl.className    = 'text-sm font-semibold text-emerald-700';
      liveDot.className         = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
    } else {
      connStatusEl.textContent  = 'Reconnecting…';
      connStatusEl.className    = 'text-sm font-semibold text-amber-600';
      liveDot.className         = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
    }
  });

  // ── Main Sensor Listener ───────────────────────────────────────
  db.ref("drybox").on("value", snapshot => {
    const data = snapshot.val();
    if (!data) return;

    const now  = new Date().toLocaleTimeString();
    const temp = parseFloat(data.temperature ?? 0);
    const hum  = parseFloat(data.humidity ?? 0);

    // Update stat cards
    tempEl.textContent      = temp.toFixed(1);
    tempTimeEl.textContent  = now;
    humEl.textContent       = hum.toFixed(1);
    humBar.style.width      = Math.min(hum, 100) + '%';
    lastUpdateEl.textContent = now;

    applyStatus(data.status, hum);

    // Push to chart
    humidityChart.data.labels.push(now);
    humidityChart.data.datasets[0].data.push(hum);
    if (humidityChart.data.labels.length > 20) {
      humidityChart.data.labels.shift();
      humidityChart.data.datasets[0].data.shift();
    }
    humidityChart.update();
  });
</script>
@endsection
