<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'DryBox AI')</title>

    {{-- ── PWA ──────────────────────────────────────────────────── --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1a56db">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="DryBox AI">

    {{-- iOS / Safari PWA --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DryBox AI">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    
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
        body { background-color: #f7f9fc; color: #191c1e; }

        /* ── Global placeholder contrast fix ──────────────────────── */
        ::placeholder { color: #64748b; opacity: 1; }       /* slate-500 */
        input, textarea, select { color: #0f172a; }         /* slate-900 */

        /* ── Sidebar nav link hover ───────────────────────────────── */
        .nav-link { border-radius: 10px; transition: background 0.15s, color 0.15s; }
        .nav-link:hover { background: #f1f5f9; color: #1e3a8a; }
        .nav-link.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }
        .nav-link.active .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24; }

        /* ── Bottom nav active dot ────────────────────────────────── */
        .bnav-link { transition: color 0.15s; }
        .bnav-link.active { color: #1d4ed8; }
        .bnav-link.active .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24; }
    </style>
    @yield('head')
</head>
<body class="text-on-background">

    {{-- ── Top Bar (logo + user only — NO page links) ─────────────── --}}
    <header class="fixed top-0 w-full z-50 flex justify-between items-center px-5 h-16 bg-white border-b border-slate-200 shadow-sm">

        {{-- Logo --}}
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow shadow-blue-500/30">
                <span class="material-symbols-outlined text-white" style="font-size:18px">humidity_indoor</span>
            </div>
            <span class="text-lg font-bold text-slate-900 tracking-tight font-['Space_Grotesk']">DryBox <span class="text-blue-600">AI</span></span>
        </div>

        {{-- User + Logout --}}
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-2 bg-slate-100 rounded-full px-3 py-1.5">
                <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-xs font-bold leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                </div>
                <span class="text-sm font-semibold text-slate-800 hidden sm:inline">{{ Auth::user()->name }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Sign out">
                    <span class="material-symbols-outlined" style="font-size:18px">logout</span>
                    <span class="hidden sm:inline">Sign out</span>
                </button>
            </form>
        </div>
    </header>

    {{-- ── Sidebar (desktop only) ──────────────────────────────────── --}}
    <aside class="hidden lg:flex flex-col fixed left-0 top-0 h-full z-40 w-60 bg-white border-r border-slate-200">

        {{-- Sidebar top spacer (aligns with header height) --}}
        <div class="h-16 flex items-center px-5 border-b border-slate-100">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Navigation</p>
        </div>

        {{-- Nav links --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5">
            @php
                $navItems = [
                    ['route' => 'dashboard',  'icon' => 'dashboard',   'label' => 'Dashboard'],
                    ['route' => 'device',     'icon' => 'devices',     'label' => 'Device'],
                    ['route' => 'silica.log', 'icon' => 'science',     'label' => 'Silica Log'],
                    ['route' => 'analytics',  'icon' => 'insights',    'label' => 'Analytics'],
                ];
            @endphp
            @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="nav-link flex items-center gap-3 px-4 py-3 text-sm text-slate-700 {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:22px">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
            @endforeach
        </nav>

        {{-- Sidebar footer --}}
        <div class="px-5 py-4 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 leading-relaxed">DryBox AI — Smart Dry Storage</p>
        </div>
    </aside>

    {{-- ── Main Content ─────────────────────────────────────────────── --}}
    <main class="lg:pl-60 pt-16 pb-20 lg:pb-6 min-h-screen">
        <div class="px-5 md:px-8 py-6">
            @yield('content')
        </div>
    </main>

    {{-- ── Bottom Nav (mobile only) ────────────────────────────────── --}}
    <nav class="lg:hidden fixed bottom-0 left-0 w-full z-50 bg-white border-t border-slate-200 shadow-lg">
        <div class="flex justify-around items-center h-16">
            @php
                $mobileNav = [
                    ['route' => 'dashboard',  'icon' => 'dashboard',   'label' => 'Dashboard'],
                    ['route' => 'device',     'icon' => 'devices',     'label' => 'Device'],
                    ['route' => 'silica.log', 'icon' => 'science',     'label' => 'Silica'],
                    ['route' => 'analytics',  'icon' => 'insights',    'label' => 'Analytics'],
                ];
            @endphp
            @foreach($mobileNav as $item)
            <a href="{{ route($item['route']) }}"
               class="bnav-link flex flex-col items-center gap-0.5 px-3 text-slate-400 {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:24px">{{ $item['icon'] }}</span>
                <span class="text-[10px] font-semibold tracking-wide">{{ $item['label'] }}</span>
            </a>
            @endforeach
        </div>
    </nav>

    @yield('scripts')

    {{-- ── Service Worker registration ────────────────────────── --}}
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('[PWA] Service worker registered:', reg.scope))
            .catch(err => console.warn('[PWA] SW registration failed:', err));
        });
      }
    </script>
</body>
</html>
