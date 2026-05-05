<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DryBox AI — Smart Dry Storage Monitoring</title>
  <meta name="description" content="AI-powered real-time humidity & temperature monitoring for precision dry storage.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0..1&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #080d1a; }
    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); }
    .gradient-text { background: linear-gradient(135deg,#60a5fa,#a78bfa 50%,#38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .btn-primary { background: linear-gradient(135deg,#2b5bb5,#0061a4); transition: all .2s; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(43,91,181,.45); }
    .hero-glow { background: radial-gradient(ellipse at 30% 40%, rgba(43,91,181,.18) 0%, transparent 60%), radial-gradient(ellipse at 80% 20%, rgba(96,165,250,.1) 0%, transparent 50%); }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
    .floating { animation: float 5s ease-in-out infinite; }
    @keyframes blink { 0%,100%{opacity:.4} 50%{opacity:1} }
    .blink { animation: blink 1.8s ease-in-out infinite; }
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
  </style>
</head>
<body class="hero-glow text-white min-h-screen">

{{-- ── Navbar ──────────────────────────────────────────────── --}}
<nav class="fixed top-0 w-full z-50 border-b border-white/5 bg-black/30 backdrop-blur-lg">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
        <span class="material-symbols-outlined text-white" style="font-size:18px">humidity_indoor</span>
      </div>
      <span class="font-display font-bold text-lg tracking-tight">DryBox <span class="text-blue-400">AI</span></span>
    </div>
    <div class="flex items-center gap-3">
      <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition-colors font-medium px-4 py-2">Sign In</a>
      <a href="{{ route('register') }}" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold text-white">Get Started</a>
    </div>
  </div>
</nav>

{{-- ── Hero ─────────────────────────────────────────────────── --}}
<section class="min-h-screen flex items-center px-6 pt-24 pb-16">
  <div class="max-w-6xl mx-auto w-full grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

    {{-- Copy --}}
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs font-bold uppercase tracking-wider mb-8">
        <span class="w-2 h-2 rounded-full bg-blue-400 blink"></span>
        Firebase Real-Time Monitoring
      </div>
      <h1 class="font-display font-bold text-5xl md:text-6xl leading-[1.1] mb-6">
        Smart Dry Box<br><span class="gradient-text">Monitoring System</span>
      </h1>
      <p class="text-slate-400 text-lg leading-relaxed mb-10 max-w-md">
        Live humidity, temperature & status updates streamed directly from your IoT sensor — protecting your equipment 24/7.
      </p>
      <div class="flex flex-col sm:flex-row gap-4">
        <a href="{{ route('register') }}" class="btn-primary px-8 py-3.5 rounded-xl font-semibold text-center text-white flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-base">rocket_launch</span> Start Monitoring Free
        </a>
        <a href="{{ route('login') }}" class="px-8 py-3.5 rounded-xl font-semibold text-center text-slate-300 border border-white/10 hover:border-white/25 hover:text-white transition-all flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-base">login</span> Sign In
        </a>
      </div>
      <div class="flex items-center gap-8 mt-12 pt-8 border-t border-white/5">
        <div><p class="font-display font-bold text-2xl">99.9%</p><p class="text-slate-500 text-xs uppercase tracking-wider mt-1">Uptime</p></div>
        <div class="w-px h-10 bg-white/10"></div>
        <div><p class="font-display font-bold text-2xl">Live</p><p class="text-slate-500 text-xs uppercase tracking-wider mt-1">Real-Time</p></div>
        <div class="w-px h-10 bg-white/10"></div>
        <div><p class="font-display font-bold text-2xl">IoT</p><p class="text-slate-500 text-xs uppercase tracking-wider mt-1">Connected</p></div>
      </div>
    </div>

    {{-- Floating Dashboard Preview --}}
    <div class="flex justify-center">
      <div class="floating w-full max-w-sm relative">
        <div class="glass rounded-2xl p-6 shadow-2xl shadow-black/60">
          <div class="flex items-center justify-between mb-5">
            <div>
              <p class="text-slate-500 text-[10px] uppercase tracking-widest">Live Sensor</p>
              <p class="font-display font-semibold text-white text-lg">Drybox Unit #1</p>
            </div>
            <div class="flex items-center gap-1.5 bg-emerald-500/10 px-3 py-1.5 rounded-full border border-emerald-500/20">
              <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 blink"></div>
              <span class="text-emerald-400 text-xs font-bold">LIVE</span>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="bg-white/5 rounded-xl p-4 border border-white/5">
              <div class="flex items-center gap-1.5 mb-2">
                <span class="material-symbols-outlined text-orange-400" style="font-size:16px">thermostat</span>
                <span class="text-slate-500 text-[10px] uppercase tracking-wider">Temp</span>
              </div>
              <p class="font-display font-bold text-3xl text-white"><span id="hero-temp">26.4</span><span class="text-base text-slate-400">°C</span></p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 border border-white/5">
              <div class="flex items-center gap-1.5 mb-2">
                <span class="material-symbols-outlined text-blue-400" style="font-size:16px">water_drop</span>
                <span class="text-slate-500 text-[10px] uppercase tracking-wider">Humidity</span>
              </div>
              <p class="font-display font-bold text-3xl text-white"><span id="hero-hum">32.1</span><span class="text-base text-slate-400">%</span></p>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-500">Humidity Level</span>
              <span class="text-emerald-400 font-semibold" id="hero-status-badge">SAFE</span>
            </div>
            <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-blue-600 to-blue-400 rounded-full transition-all duration-1000" id="hero-bar" style="width:32%"></div>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-white/5 flex justify-between text-xs text-slate-600">
            <span>iot-project-1d0fa-rtdb</span>
            <span id="hero-time">--:--:--</span>
          </div>
        </div>
        {{-- Floating badges --}}
        <div class="absolute -right-6 top-6 glass rounded-xl px-3 py-2 text-xs shadow-xl" style="animation:float 4s ease-in-out infinite .5s">
          <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-emerald-400" style="font-size:14px">check_circle</span><span class="text-slate-300 font-medium">Status: Safe</span></div>
        </div>
        <div class="absolute -left-6 bottom-6 glass rounded-xl px-3 py-2 text-xs shadow-xl" style="animation:float 6s ease-in-out infinite 1s">
          <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-blue-400" style="font-size:14px">sensors</span><span class="text-slate-300 font-medium">Firebase Sync</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── Features ─────────────────────────────────────────────── --}}
<section class="py-24 px-6">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-16">
      <span class="text-blue-400 text-xs font-bold uppercase tracking-widest block mb-3">Why DryBox AI</span>
      <h2 class="font-display font-bold text-4xl mb-4">Everything you need to protect your equipment</h2>
      <p class="text-slate-400 text-lg max-w-xl mx-auto">Built for precision environments where humidity and temperature control is critical.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="glass rounded-2xl p-8 group hover:-translate-y-1 hover:border-blue-500/30 transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
          <span class="material-symbols-outlined text-blue-400">sensors</span>
        </div>
        <h3 class="font-display font-semibold text-xl mb-3">Real-Time Monitoring</h3>
        <p class="text-slate-400 leading-relaxed">Live sensor data streamed directly from Firebase — temperature, humidity, and status updated the moment they change.</p>
      </div>
      <div class="glass rounded-2xl p-8 group hover:-translate-y-1 hover:border-violet-500/30 transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
          <span class="material-symbols-outlined text-violet-400">crisis_alert</span>
        </div>
        <h3 class="font-display font-semibold text-xl mb-3">Smart Alerts</h3>
        <p class="text-slate-400 leading-relaxed">Automatic Safe / Warning / Critical classification with instant visual banners when your thresholds are exceeded.</p>
      </div>
      <div class="glass rounded-2xl p-8 group hover:-translate-y-1 hover:border-cyan-500/30 transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
          <span class="material-symbols-outlined text-cyan-400">insights</span>
        </div>
        <h3 class="font-display font-semibold text-xl mb-3">Live Analytics</h3>
        <p class="text-slate-400 leading-relaxed">Dynamic Chart.js graphs plotting humidity and temperature trends in real-time — your sensor history at a glance.</p>
      </div>
    </div>
  </div>
</section>

{{-- ── CTA ───────────────────────────────────────────────────── --}}
<section class="py-24 px-6">
  <div class="max-w-3xl mx-auto text-center">
    <div class="glass rounded-3xl p-12 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-600/10 to-violet-600/10 pointer-events-none"></div>
      <div class="relative z-10">
        <h2 class="font-display font-bold text-4xl mb-4">Ready to monitor your sensor?</h2>
        <p class="text-slate-400 text-lg mb-8">Create a free account and see your Firebase sensor data in a beautiful live dashboard.</p>
        <a href="{{ route('register') }}" class="btn-primary inline-flex items-center gap-2 px-10 py-4 rounded-xl font-bold text-white text-lg">
          <span class="material-symbols-outlined">rocket_launch</span> Create Your Account
        </a>
      </div>
    </div>
  </div>
</section>

{{-- ── Footer ───────────────────────────────────────────────── --}}
<footer class="py-8 px-6 border-t border-white/5">
  <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 rounded bg-blue-600 flex items-center justify-center">
        <span class="material-symbols-outlined text-white" style="font-size:14px">humidity_indoor</span>
      </div>
      <span class="font-display font-semibold text-sm">DryBox AI</span>
    </div>
    <p class="text-slate-600 text-sm">© {{ date('Y') }} DryBox AI. Built with Laravel & Firebase.</p>
  </div>
</footer>

<script>
  // Animate the hero mock card with simulated sensor-like variance
  let baseTemp = 26.4, baseHum = 32.1;
  function animateHero() {
    baseTemp += (Math.random() - 0.5) * 0.3;
    baseHum  += (Math.random() - 0.5) * 0.6;
    baseTemp  = Math.max(22, Math.min(35, baseTemp));
    baseHum   = Math.max(20, Math.min(55, baseHum));
    document.getElementById('hero-temp').textContent = baseTemp.toFixed(1);
    document.getElementById('hero-hum').textContent  = baseHum.toFixed(1);
    document.getElementById('hero-bar').style.width  = baseHum.toFixed(1) + '%';
    document.getElementById('hero-time').textContent = new Date().toLocaleTimeString();
  }
  setInterval(animateHero, 2000);
  animateHero();
</script>
</body>
</html>
