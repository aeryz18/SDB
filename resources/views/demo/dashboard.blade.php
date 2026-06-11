@extends('layouts.demo')

@section('title', 'DryBox AI — Demo Dashboard')

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
        <div class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-outline-variant rounded-xl self-start" id="tour-connection-badge">
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" id="live-dot"></div>
            <span class="text-sm font-semibold text-emerald-700" id="connection-status">Simulated</span>
        </div>
    </div>

    {{-- ── Dynamic Alert Banner ──────── --}}
    <section id="alert-banner" id="tour-alert-banner"></section>

    {{-- ── Live Sensor Stat Cards ─────────────────────────────────── --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-grid-gutter" id="tour-sensor-cards">

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
                        <span class="font-headline-md text-headline-md text-emerald-600" id="status-value">--</span>
                    </div>
                </div>
                <div class="p-3 bg-emerald-50 rounded-xl group-hover:scale-110 transition-transform" id="status-icon-wrap">
                    <span class="material-symbols-outlined text-emerald-500" id="status-icon">check_circle</span>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-emerald-500" id="status-dot"></div>
                <span class="text-xs text-on-surface-variant" id="status-sub">Simulated sensor data</span>
            </div>
        </div>

    </section>

    {{-- ── Real-Time Humidity Chart ─────────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-md" id="tour-chart">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Live Humidity Trend</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Simulated readings (last 20 points)</p>
            </div>
            <span class="flex items-center gap-2 text-xs text-violet-600 font-bold uppercase tracking-wider bg-violet-50 px-3 py-1.5 rounded-full border border-violet-200">
                <div class="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></div>
                Demo
            </span>
        </div>
        <div class="relative h-64">
            <canvas id="humidity-chart"></canvas>
        </div>
    </section>

    {{-- ── Alert Banner Placeholder ────────────────────────────── --}}
    <section id="tour-alert-banner">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-md flex items-start gap-4">
            <div class="p-3 bg-emerald-500 rounded-full text-white flex-shrink-0">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <div>
                <h3 class="font-headline-md text-emerald-800">SAFE: Drybox Sensor</h3>
                <p class="font-body-sm text-emerald-600 mt-1">All conditions within safe limits — humidity is well below threshold.</p>
            </div>
        </div>
    </section>

    {{-- ── Connection Footer ────────────────────────────────────── --}}
    <section class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-surface-container-low border border-outline-variant rounded-xl p-md gap-4" id="tour-connection">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary">cloud_sync</span>
            <div>
                <span class="font-label-caps text-label-caps text-on-surface-variant block">FIREBASE REALTIME DATABASE</span>
                <span class="font-body-sm text-body-sm text-on-surface font-mono text-xs">demo-mode-simulated.firebaseio.com</span>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  // ── DEMO: Simulated Sensor Data ─────────────────────────────
  const CRIT_THRESH = parseInt(localStorage.getItem('critThreshold') ?? 45);
  const WARN_THRESH = parseInt(localStorage.getItem('warnThreshold') ?? 35);

  // DOM refs
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
  const alertBanner   = document.getElementById('tour-alert-banner');

  // Chart.js Setup
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
        tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y.toFixed(1)} %` } }
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

  // ── Simulated sensor scenarios ────────────────────────────────
  // Cycle through: normal → rising → warning → dropping → safe
  let simTemp = 25.5, simHum = 28.0;
  let tick = 0;
  const SCENARIO_LEN = 60; // ticks per cycle

  function applyDemoStatus(humidity) {
    const isCritical = humidity > CRIT_THRESH;
    const isWarning  = humidity > WARN_THRESH && !isCritical;

    if (isCritical) {
      statusEl.textContent      = 'Critical';
      statusEl.className        = 'font-headline-md text-headline-md text-red-600';
      statusDot.className       = 'w-2 h-2 rounded-full bg-red-500 animate-pulse';
      statusIcon.textContent    = 'crisis_alert';
      statusIconWrap.className  = 'p-3 bg-red-50 rounded-xl group-hover:scale-110 transition-transform';
      statusIcon.className      = 'material-symbols-outlined text-red-500';
      statusSub.textContent     = 'Critical — immediate action required!';
      humBar.className          = 'h-full bg-red-500 transition-all duration-1000 rounded-full';
      alertBanner.innerHTML     = `
        <div class="bg-red-50 border border-red-200 rounded-xl p-md flex items-start gap-4">
          <div class="p-3 bg-red-500 rounded-full text-white flex-shrink-0">
            <span class="material-symbols-outlined">crisis_alert</span>
          </div>
          <div>
            <h3 class="font-headline-md text-red-800">CRITICAL: Drybox Sensor</h3>
            <p class="font-body-sm text-red-600 mt-1">Humidity at ${humidity.toFixed(1)}% — desiccant may be saturated!</p>
          </div>
        </div>`;
    } else if (isWarning) {
      statusEl.textContent      = 'Warning';
      statusEl.className        = 'font-headline-md text-headline-md text-amber-600';
      statusDot.className       = 'w-2 h-2 rounded-full bg-amber-500 animate-pulse';
      statusIcon.textContent    = 'warning';
      statusIconWrap.className  = 'p-3 bg-amber-50 rounded-xl group-hover:scale-110 transition-transform';
      statusIcon.className      = 'material-symbols-outlined text-amber-500';
      statusSub.textContent     = 'Humidity approaching critical threshold';
      humBar.className          = 'h-full bg-amber-500 transition-all duration-1000 rounded-full';
      alertBanner.innerHTML     = `
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-md flex items-start gap-4">
          <div class="p-3 bg-amber-500 rounded-full text-white flex-shrink-0">
            <span class="material-symbols-outlined">warning</span>
          </div>
          <div>
            <h3 class="font-headline-md text-amber-800">WARNING: Drybox Sensor</h3>
            <p class="font-body-sm text-amber-600 mt-1">Humidity at ${humidity.toFixed(1)}% — approaching critical threshold.</p>
          </div>
        </div>`;
    } else {
      statusEl.textContent      = 'Safe';
      statusEl.className        = 'font-headline-md text-headline-md text-emerald-600';
      statusDot.className       = 'w-2 h-2 rounded-full bg-emerald-500';
      statusIcon.textContent    = 'check_circle';
      statusIconWrap.className  = 'p-3 bg-emerald-50 rounded-xl group-hover:scale-110 transition-transform';
      statusIcon.className      = 'material-symbols-outlined text-emerald-500';
      statusSub.textContent     = 'All conditions within safe limits';
      humBar.className          = 'h-full bg-blue-500 transition-all duration-1000 rounded-full';
      alertBanner.innerHTML     = `
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-md flex items-start gap-4">
          <div class="p-3 bg-emerald-500 rounded-full text-white flex-shrink-0">
            <span class="material-symbols-outlined">check_circle</span>
          </div>
          <div>
            <h3 class="font-headline-md text-emerald-800">SAFE: Drybox Sensor</h3>
            <p class="font-body-sm text-emerald-600 mt-1">All conditions within safe limits — humidity is well below threshold.</p>
          </div>
        </div>`;
    }
  }

  function simulateSensor() {
    const phase = tick % SCENARIO_LEN;

    // Gradually rise to warning/critical then fall back
    if (phase < 20) {
      // Gradual rise
      simHum  += (Math.random() * 1.2) + 0.1;
      simTemp += (Math.random() - 0.4) * 0.2;
    } else if (phase < 35) {
      // Peak / plateau near warning
      simHum  += (Math.random() - 0.3) * 0.5;
      simTemp += (Math.random() - 0.5) * 0.15;
    } else if (phase < 45) {
      // Spike into critical territory
      simHum  += (Math.random() * 0.8);
      simTemp += (Math.random() - 0.3) * 0.2;
    } else {
      // Recovery — drop back to safe
      simHum  -= (Math.random() * 1.5) + 0.3;
      simTemp -= (Math.random() - 0.4) * 0.15;
    }

    // Clamp values
    simHum  = Math.max(22, Math.min(55, simHum));
    simTemp = Math.max(22, Math.min(36, simTemp));

    // Reset to safe zone at end of cycle
    if (phase === SCENARIO_LEN - 1) {
      simHum  = 26 + Math.random() * 4;
      simTemp = 25 + Math.random() * 2;
    }

    const now = new Date().toLocaleTimeString();

    // Update cards
    tempEl.textContent       = simTemp.toFixed(1);
    tempTimeEl.textContent   = now;
    humEl.textContent        = simHum.toFixed(1);
    humBar.style.width       = Math.min(simHum, 100) + '%';
    lastUpdateEl.textContent = now;

    applyDemoStatus(simHum);

    // Push to chart
    humidityChart.data.labels.push(now);
    humidityChart.data.datasets[0].data.push(parseFloat(simHum.toFixed(1)));
    if (humidityChart.data.labels.length > 20) {
      humidityChart.data.labels.shift();
      humidityChart.data.datasets[0].data.shift();
    }
    humidityChart.update();

    tick++;
  }

  // Seed initial data points for the chart
  for (let i = 0; i < 8; i++) {
    const fakeHum = 26 + Math.random() * 6;
    const t = new Date(Date.now() - (8 - i) * 2000).toLocaleTimeString();
    humidityChart.data.labels.push(t);
    humidityChart.data.datasets[0].data.push(parseFloat(fakeHum.toFixed(1)));
  }
  humidityChart.update();

  // Run simulation every 2 seconds
  simulateSensor();
  setInterval(simulateSensor, 2000);
</script>
@endsection
