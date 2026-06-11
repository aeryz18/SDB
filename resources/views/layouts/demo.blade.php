<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'DryBox AI — Demo Mode')</title>
    <meta name="description" content="Explore DryBox AI's smart dry storage monitoring dashboard in interactive demo mode — no account required.">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-secondary-fixed": "#001d36",
                        "primary-fixed-dim": "#b0c6ff",
                        "inverse-surface": "#2d3133",
                        "surface-variant": "#e0e3e6",
                        "on-secondary": "#ffffff",
                        "inverse-on-surface": "#eff1f4",
                        "primary-fixed": "#d9e2ff",
                        "on-tertiary-container": "#7cc3ff",
                        "on-secondary-fixed-variant": "#00497d",
                        "on-tertiary-fixed-variant": "#004b74",
                        "on-background": "#191c1e",
                        "background": "#f7f9fc",
                        "surface": "#f7f9fc",
                        "surface-bright": "#f7f9fc",
                        "outline": "#737783",
                        "on-tertiary": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#003859",
                        "on-surface-variant": "#434652",
                        "surface-container-high": "#e6e8eb",
                        "tertiary-fixed-dim": "#94ccff",
                        "tertiary-container": "#00507c",
                        "on-primary-container": "#a1bbff",
                        "on-secondary-container": "#00355c",
                        "on-primary-fixed": "#001945",
                        "primary-container": "#0d47a1",
                        "surface-container-highest": "#e0e3e6",
                        "on-surface": "#191c1e",
                        "on-primary-fixed-variant": "#00429c",
                        "secondary-fixed-dim": "#9ecaff",
                        "surface-container": "#eceef1",
                        "on-tertiary-fixed": "#001d32",
                        "surface-dim": "#d8dadd",
                        "secondary": "#0061a4",
                        "surface-tint": "#2b5bb5",
                        "surface-container-low": "#f2f4f7",
                        "on-primary": "#ffffff",
                        "error-container": "#ffdad6",
                        "tertiary-fixed": "#cde5ff",
                        "on-error": "#ffffff",
                        "error": "#ba1a1a",
                        "secondary-fixed": "#d1e4ff",
                        "secondary-container": "#33a0fd",
                        "inverse-primary": "#b0c6ff",
                        "on-error-container": "#93000a",
                        "primary": "#003178",
                        "outline-variant": "#c3c6d4"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "md": "24px",
                        "grid-gutter": "24px",
                        "base": "8px",
                        "sm": "12px",
                        "lg": "40px",
                        "xl": "64px",
                        "container-margin": "32px",
                        "xs": "4px"
                    },
                    "fontFamily": {
                        "display-lg": ["Space Grotesk"],
                        "body-sm": ["Inter"],
                        "title-sm": ["Inter"],
                        "headline-md": ["Space Grotesk"],
                        "body-base": ["Inter"],
                        "label-caps": ["Inter"],
                        "data-num": ["Space Grotesk"]
                    },
                    "fontSize": {
                        "display-lg": ["32px", {"lineHeight": "1.2", "fontWeight": "700"}],
                        "body-sm": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "title-sm": ["18px", {"lineHeight": "1.5", "fontWeight": "600"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                        "body-base": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                        "label-caps": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700"}],
                        "data-num": ["48px", {"lineHeight": "1", "fontWeight": "500"}]
                    }
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { background-color: #f7f9fc; }

        /* ── Demo Tour Styles ────────────────────────────────── */
        .tour-overlay {
            position: fixed; inset: 0; z-index: 9998;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(2px);
            transition: opacity .3s ease;
        }
        .tour-spotlight {
            position: absolute; z-index: 9999;
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);
            transition: all .45s cubic-bezier(.4,0,.2,1);
            pointer-events: none;
        }
        .tour-tooltip {
            position: absolute; z-index: 10000;
            background: white;
            border-radius: 16px;
            padding: 24px 28px;
            max-width: 360px;
            box-shadow: 0 24px 64px rgba(0,0,0,.18), 0 0 0 1px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.4,0,.2,1);
            font-family: 'Inter', sans-serif;
        }
        .tour-tooltip::before {
            content: '';
            position: absolute;
            width: 14px; height: 14px;
            background: white;
            transform: rotate(45deg);
        }
        .tour-tooltip.arrow-top::before    { top: -7px; left: 28px; }
        .tour-tooltip.arrow-bottom::before { bottom: -7px; left: 28px; }
        .tour-tooltip.arrow-left::before   { left: -7px; top: 28px; }
        .tour-tooltip.arrow-right::before  { right: -7px; top: 28px; }

        /* Demo banner shimmer */
        @keyframes demo-shimmer {
            0%   { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .demo-shimmer {
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
            background-size: 200% 100%;
            animation: demo-shimmer 3s linear infinite;
        }

        /* Pulse ring for CTA */
        @keyframes pulse-ring {
            0%   { transform: scale(.85); opacity: 1; }
            100% { transform: scale(2.2);  opacity: 0; }
        }
        .pulse-ring::before {
            content: '';
            position: absolute; inset: -4px;
            border-radius: inherit;
            border: 2px solid currentColor;
            animation: pulse-ring 1.5s cubic-bezier(0,.2,.4,1) infinite;
        }
    </style>
    @yield('head')
</head>
<body class="text-on-background">

    {{-- ── Demo Mode Top Banner ───────────────────────────────── --}}
    <div class="fixed top-0 left-0 right-0 z-[60] bg-gradient-to-r from-violet-600 via-blue-600 to-cyan-500 text-white" id="demo-banner">
        <div class="relative overflow-hidden">
            <div class="demo-shimmer absolute inset-0"></div>
            <div class="relative max-w-7xl mx-auto px-6 py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-white/15 px-2.5 py-1 rounded-full backdrop-blur-sm">
                        <span class="material-symbols-outlined text-amber-300" style="font-size:16px">science</span>
                        <span class="text-xs font-bold uppercase tracking-wider">Demo Mode</span>
                    </div>
                    <span class="text-sm text-white/80 hidden sm:inline">Exploring with simulated sensor data — no account needed</span>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.__demoTour && window.__demoTour.start()" class="text-xs font-bold bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-full transition-colors flex items-center gap-1.5" id="restart-tour-btn">
                        <span class="material-symbols-outlined" style="font-size:14px">replay</span>
                        Restart Tour
                    </button>
                    <a href="{{ route('register') }}" class="relative text-xs font-bold bg-white text-blue-700 px-4 py-1.5 rounded-full hover:bg-blue-50 transition-colors pulse-ring">
                        Create Account →
                    </a>
                    <a href="{{ route('demo.exit') }}" class="text-white/60 hover:text-white transition-colors" title="Exit Demo">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- TopNavBar -->
    <header class="fixed top-[44px] w-full z-50 flex justify-between items-center px-6 h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800" id="tour-topbar">
        <div class="flex items-center gap-4">
            <span class="text-xl font-bold text-blue-900 dark:text-blue-200 tracking-tighter font-['Space_Grotesk']">DryBox AI</span>
        </div>
        <div class="flex items-center gap-6">
            <div class="hidden md:flex gap-8 items-center font-['Space_Grotesk'] text-sm tracking-tight" id="tour-nav-links">
                <a class="{{ request()->routeIs('demo.dashboard') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('demo.dashboard') }}">Dashboard</a>
                <a class="{{ request()->routeIs('demo.equipment') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('demo.equipment') }}">Equipment</a>
                <a class="{{ request()->routeIs('demo.analytics') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('demo.analytics') }}">Analytics</a>
            </div>
            <div class="flex items-center gap-3">
                {{-- Fake demo user --}}
                <div class="hidden sm:flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-full px-3 py-1.5">
                    <div class="w-6 h-6 rounded-full bg-gradient-to-br from-violet-500 to-blue-500 flex items-center justify-center">
                        <span class="text-white text-xs font-bold">D</span>
                    </div>
                    <span class="text-sm font-medium text-slate-700">Demo User</span>
                </div>
            </div>
        </div>
    </header>

    <!-- SideNavBar -->
    <aside class="hidden lg:flex flex-col fixed left-0 top-[44px] h-full z-40 pt-20 pb-6 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 font-['Space_Grotesk'] text-sm font-medium" id="tour-sidebar">
        <div class="px-6 mb-8">
            <h2 class="text-lg font-black text-blue-900 dark:text-blue-100">Precision Monitor</h2>
            <p class="text-[10px] uppercase tracking-widest text-slate-400">AI-Powered Integrity</p>
        </div>
        <nav class="flex-1 space-y-1 px-4" id="tour-sidebar-nav">
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('demo.dashboard') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('demo.dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('demo.equipment') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('demo.equipment') }}">
                <span class="material-symbols-outlined">inventory_2</span> Equipment
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('demo.analytics') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('demo.analytics') }}">
                <span class="material-symbols-outlined">insights</span> Analytics
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('demo.settings') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('demo.settings') }}">
                <span class="material-symbols-outlined">settings</span> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content (offset for demo banner + topbar) -->
    <main class="lg:pl-64 pt-[108px] pb-24 lg:pb-8 px-container-margin">
        @yield('content')
    </main>

    <!-- BottomNavBar (Mobile) -->
    <nav class="lg:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 py-3 pb-safe bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 font-['Space_Grotesk'] text-[10px] uppercase tracking-widest">
        <a class="flex flex-col items-center {{ request()->routeIs('demo.dashboard') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('demo.dashboard') }}">
            <span class="material-symbols-outlined mb-1">home</span> Home
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('demo.equipment') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('demo.equipment') }}">
            <span class="material-symbols-outlined mb-1">grid_view</span> Units
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('demo.analytics') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('demo.analytics') }}">
            <span class="material-symbols-outlined mb-1">query_stats</span> Data
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('demo.settings') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('demo.settings') }}">
            <span class="material-symbols-outlined mb-1">settings</span> Settings
        </a>
    </nav>

    {{-- ── Tour Container ─────────────────────────────────────── --}}
    <div id="tour-container"></div>

    @yield('scripts')

    {{-- ── Guided Tour Engine ─────────────────────────────────── --}}
    <script>
    (function() {
        // ── Tour Step Definitions (per-page) ────────────────────
        const PAGE_TOURS = {
            'demo.dashboard': [
                {
                    target: '#tour-sidebar-nav',
                    title: '📍 Navigation',
                    text: 'Use the sidebar to switch between Dashboard, Equipment, Analytics, and Settings. Each page shows different aspects of your dry box monitoring.',
                    arrow: 'left',
                    offsetX: 20, offsetY: -20
                },
                {
                    target: '#tour-sensor-cards',
                    title: '📊 Live Sensor Cards',
                    text: 'These cards display real-time Temperature, Humidity, and Status readings from your IoT sensor. In this demo, data is simulated to showcase the experience.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-alert-banner',
                    title: '🚨 Smart Alert System',
                    text: 'When humidity exceeds your configured thresholds, a color-coded alert banner appears instantly — Safe (green), Warning (amber), or Critical (red).',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-chart',
                    title: '📈 Real-Time Chart',
                    text: 'Watch humidity trends as they happen! The chart updates live with every Firebase push event, keeping the last 20 data points visible.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-connection',
                    title: '🔗 Firebase Connection',
                    text: 'This footer shows your connection status to Firebase Realtime Database. In demo mode, we simulate this connection for you.',
                    arrow: 'bottom',
                    offsetX: 0, offsetY: -16
                }
            ],
            'demo.equipment': [
                {
                    target: '#tour-stats-bar',
                    title: '📋 Summary Stats',
                    text: 'Quick overview of all connected units — current temperature, humidity, status, and the last sensor reading timestamp.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-equipment-grid',
                    title: '🗃️ Equipment Cards',
                    text: 'Each card represents a connected dry box unit. Click "View Details" to open a detailed modal with full sensor information.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                }
            ],
            'demo.analytics': [
                {
                    target: '#tour-kpi-tiles',
                    title: '📊 KPI Dashboard',
                    text: 'Track average humidity, peak humidity, average temperature, and total readings in your monitoring session.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-analytics-chart',
                    title: '📉 Dual-Axis Chart',
                    text: 'This chart plots both humidity (blue, left axis) and temperature (orange, right axis) simultaneously — giving you a complete environmental picture.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-event-log',
                    title: '📝 Event Log',
                    text: 'Every sensor reading is logged here with status badges. You can also export all session data to CSV for external analysis.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                }
            ],
            'demo.settings': [
                {
                    target: '#tour-thresholds',
                    title: '⚙️ Alert Thresholds',
                    text: 'Drag the sliders to set your Warning and Critical humidity levels. Changes are saved locally and affect alert banners across all pages.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                },
                {
                    target: '#tour-firebase-section',
                    title: '🔌 Firebase Config',
                    text: 'View and test your Firebase connection. In a real setup, your IoT sensor pushes data here via the Realtime Database.',
                    arrow: 'top',
                    offsetX: 0, offsetY: 16
                }
            ]
        };

        class DemoTour {
            constructor(pageName) {
                this.pageName = pageName;
                this.steps = PAGE_TOURS[pageName] || [];
                this.currentStep = 0;
                this.container = document.getElementById('tour-container');
                this.active = false;
            }

            start() {
                if (!this.steps.length) return;
                this.currentStep = 0;
                this.active = true;
                this.render();
            }

            stop() {
                this.active = false;
                this.container.innerHTML = '';
                // Mark this page as toured
                sessionStorage.setItem('toured_' + this.pageName, '1');
            }

            render() {
                if (!this.active || this.currentStep >= this.steps.length) {
                    this.stop();
                    return;
                }

                const step = this.steps[this.currentStep];
                const el = document.querySelector(step.target);

                if (!el) {
                    this.currentStep++;
                    this.render();
                    return;
                }

                const rect = el.getBoundingClientRect();
                const padding = 12;
                const scrollY = window.scrollY;
                const scrollX = window.scrollX;

                // Scroll element into view
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });

                setTimeout(() => {
                    const rect2 = el.getBoundingClientRect();
                    const spotTop  = rect2.top + window.scrollY - padding;
                    const spotLeft = rect2.left + window.scrollX - padding;
                    const spotW    = rect2.width + padding * 2;
                    const spotH    = rect2.height + padding * 2;

                    // Tooltip position
                    let ttTop, ttLeft;
                    const arrowDir = step.arrow || 'top';

                    if (arrowDir === 'top') {
                        ttTop  = spotTop + spotH + 16 + (step.offsetY || 0);
                        ttLeft = spotLeft + (step.offsetX || 0);
                    } else if (arrowDir === 'bottom') {
                        ttTop  = spotTop - 200 + (step.offsetY || 0);
                        ttLeft = spotLeft + (step.offsetX || 0);
                    } else if (arrowDir === 'left') {
                        ttTop  = spotTop + (step.offsetY || 0);
                        ttLeft = spotLeft + spotW + 16 + (step.offsetX || 0);
                    } else {
                        ttTop  = spotTop + (step.offsetY || 0);
                        ttLeft = spotLeft - 380 + (step.offsetX || 0);
                    }

                    // Clamp to viewport
                    ttLeft = Math.max(20, Math.min(ttLeft, window.innerWidth - 400));

                    const stepNum = this.currentStep + 1;
                    const total   = this.steps.length;
                    const isLast  = stepNum === total;

                    this.container.innerHTML = `
                        <div class="tour-overlay" id="tour-overlay"></div>
                        <div class="tour-spotlight" style="top:${spotTop}px;left:${spotLeft}px;width:${spotW}px;height:${spotH}px;"></div>
                        <div class="tour-tooltip arrow-${arrowDir}" style="top:${ttTop}px;left:${ttLeft}px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                <h4 style="font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:#003178;margin:0;">${step.title}</h4>
                                <button id="tour-close" style="background:none;border:none;cursor:pointer;padding:4px;color:#94a3b8;font-size:18px;">
                                    <span class="material-symbols-outlined" style="font-size:20px">close</span>
                                </button>
                            </div>
                            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 20px 0;">${step.text}</p>
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    ${Array.from({length: total}, (_, i) => `<div style="width:${i === this.currentStep ? '24px' : '8px'};height:8px;border-radius:4px;background:${i === this.currentStep ? '#003178' : '#e2e8f0'};transition:all .3s;"></div>`).join('')}
                                </div>
                                <div style="display:flex;gap:8px;">
                                    ${this.currentStep > 0 ? '<button id="tour-prev" style="padding:8px 16px;border-radius:10px;border:1px solid #e2e8f0;background:white;cursor:pointer;font-size:13px;font-weight:600;color:#64748b;transition:all .2s;">← Back</button>' : ''}
                                    <button id="tour-next" style="padding:8px 20px;border-radius:10px;border:none;background:linear-gradient(135deg,#003178,#0061a4);color:white;cursor:pointer;font-size:13px;font-weight:700;box-shadow:0 4px 12px rgba(0,49,120,.25);transition:all .2s;">
                                        ${isLast ? '✓ Finish' : 'Next →'}
                                    </button>
                                </div>
                            </div>
                            <p style="color:#94a3b8;font-size:11px;margin-top:12px;text-align:center;">${stepNum} of ${total}</p>
                        </div>
                    `;

                    // Event handlers
                    document.getElementById('tour-overlay')?.addEventListener('click', () => this.stop());
                    document.getElementById('tour-close')?.addEventListener('click', () => this.stop());
                    document.getElementById('tour-next')?.addEventListener('click', () => {
                        this.currentStep++;
                        this.render();
                    });
                    document.getElementById('tour-prev')?.addEventListener('click', () => {
                        this.currentStep--;
                        this.render();
                    });
                }, 500);
            }
        }

        // Detect current page from body data or URL
        let pageName = '';
        const path = window.location.pathname;
        if (path.includes('/demo/equipment'))  pageName = 'demo.equipment';
        else if (path.includes('/demo/analytics'))  pageName = 'demo.analytics';
        else if (path.includes('/demo/settings'))   pageName = 'demo.settings';
        else if (path.includes('/demo'))             pageName = 'demo.dashboard';

        const tour = new DemoTour(pageName);
        window.__demoTour = tour;

        // Auto-start tour on first visit to this page
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                if (!sessionStorage.getItem('toured_' + pageName)) {
                    tour.start();
                }
            }, 1200);
        });
    })();
    </script>
</body>
</html>
