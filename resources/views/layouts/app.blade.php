<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'DryBox AI')</title>
    
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
    </style>
    @yield('head')
</head>
<body class="text-on-background">
    <!-- TopNavBar -->
    <header class="fixed top-0 w-full z-50 flex justify-between items-center px-6 h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <span class="text-xl font-bold text-blue-900 dark:text-blue-200 tracking-tighter font-['Space_Grotesk']">DryBox AI</span>
        </div>
        <div class="flex items-center gap-6">
            <div class="hidden md:flex gap-8 items-center font-['Space_Grotesk'] text-sm tracking-tight">
                <a class="{{ request()->routeIs('dashboard') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="{{ request()->routeIs('equipment') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('equipment') }}">Equipment</a>
                <a class="{{ request()->routeIs('analytics') ? 'text-blue-700 font-semibold border-b-2 border-blue-700' : 'text-slate-500' }}" href="{{ route('analytics') }}">Analytics</a>
            </div>
            <div class="flex items-center gap-2">
                <button class="p-2 text-slate-500 hover:bg-slate-50 rounded-full transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <div class="w-8 h-8 rounded-full overflow-hidden ml-2 border border-slate-200">
                    <img alt="Administrator Profile" src="https://lh3.googleusercontent.com/aida-public/AB6AXuChYrniWnbZ33du5rhAJI5nAjY-PHO_z-bsDMXGL28mBYfxQxAtuaFPYOKWKQng0CliRbmJBJervXtgwU3e8kdA2BFefG9Pr1KpLDlwHttC9WaGdoWn3wpWXGZ7r3Hr8j8KZf0nxhzcjkcVePjKChgmAKMnJu_m7Isrm666LrAmszTZo_zNpSnXZj1X_kvmDSSy2PX5VpG5bnP4m9H8rOAAThotXAlZ-HHFvj3j2kaR0x7QQpZTJb_VtA_dXJmhP4cPkcbfdb8bvKk"/>
                </div>
            </div>
        </div>
    </header>

    <!-- SideNavBar -->
    <aside class="hidden lg:flex flex-col fixed left-0 top-0 h-full z-40 pt-20 pb-6 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 font-['Space_Grotesk'] text-sm font-medium">
        <div class="px-6 mb-8">
            <h2 class="text-lg font-black text-blue-900 dark:text-blue-100">Precision Monitor</h2>
            <p class="text-[10px] uppercase tracking-widest text-slate-400">AI-Powered Integrity</p>
        </div>
        <nav class="flex-1 space-y-1 px-4">
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('equipment') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('equipment') }}">
                <span class="material-symbols-outlined">inventory_2</span> Equipment
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('analytics') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('analytics') }}">
                <span class="material-symbols-outlined">insights</span> Analytics
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('settings') ? 'bg-blue-50 text-blue-800 border-r-4 border-blue-800' : 'text-slate-600' }}" href="{{ route('settings') }}">
                <span class="material-symbols-outlined">settings</span> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:pl-64 pt-20 pb-24 lg:pb-8 px-container-margin">
        @yield('content')
    </main>

    <!-- BottomNavBar (Mobile) -->
    <nav class="lg:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 py-3 pb-safe bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 font-['Space_Grotesk'] text-[10px] uppercase tracking-widest">
        <a class="flex flex-col items-center {{ request()->routeIs('dashboard') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('dashboard') }}">
            <span class="material-symbols-outlined mb-1">home</span> Home
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('equipment') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('equipment') }}">
            <span class="material-symbols-outlined mb-1">grid_view</span> Units
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('analytics') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('analytics') }}">
            <span class="material-symbols-outlined mb-1">query_stats</span> Data
        </a>
        <a class="flex flex-col items-center {{ request()->routeIs('settings') ? 'text-blue-800' : 'text-slate-400' }}" href="{{ route('settings') }}">
            <span class="material-symbols-outlined mb-1">settings</span> Settings
        </a>
    </nav>

    @yield('scripts')
</body>
</html>
