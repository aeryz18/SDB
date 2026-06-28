<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Setup') — DryBox AI</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            display: ['Space Grotesk', 'sans-serif'],
            body:    ['Inter', 'sans-serif'],
          },
          colors: {
            primary: '#1a56db',
          },
        },
      },
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

  {{-- ── Top bar ─────────────────────────────────────────────────── --}}
  <header class="bg-white border-b border-slate-200 px-6 py-4">
    <div class="max-w-2xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow shadow-blue-500/30">
          <span class="material-symbols-outlined text-white" style="font-size:18px">humidity_indoor</span>
        </div>
        <span class="font-display font-bold text-lg tracking-tight text-slate-900">DryBox <span class="text-blue-600">AI</span></span>
      </div>
      <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-slate-600 transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined" style="font-size:15px">close</span> Skip setup
      </a>
    </div>
  </header>

  {{-- ── Progress stepper ────────────────────────────────────────── --}}
  <div class="bg-white border-b border-slate-200">
    <div class="max-w-2xl mx-auto px-6 py-4">
      <div class="flex items-center gap-2">

        @php $currentStep = $currentStep ?? 1; @endphp

        {{-- Step 1 --}}
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
            {{ $currentStep > 1 ? 'bg-emerald-500 text-white' : ($currentStep === 1 ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400') }}">
            @if($currentStep > 1)
              <span class="material-symbols-outlined" style="font-size:14px">check</span>
            @else
              1
            @endif
          </div>
          <span class="text-sm font-medium {{ $currentStep === 1 ? 'text-slate-800' : 'text-slate-400' }} hidden sm:inline">Add Device</span>
        </div>

        <div class="flex-1 h-px bg-slate-200 mx-1"></div>

        {{-- Step 2 --}}
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
            {{ $currentStep > 2 ? 'bg-emerald-500 text-white' : ($currentStep === 2 ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400') }}">
            @if($currentStep > 2)
              <span class="material-symbols-outlined" style="font-size:14px">check</span>
            @else
              2
            @endif
          </div>
          <span class="text-sm font-medium {{ $currentStep === 2 ? 'text-slate-800' : 'text-slate-400' }} hidden sm:inline">Flash Firmware</span>
        </div>

        <div class="flex-1 h-px bg-slate-200 mx-1"></div>

        {{-- Step 3 --}}
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
            {{ $currentStep > 3 ? 'bg-emerald-500 text-white' : ($currentStep === 3 ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400') }}">
            @if($currentStep > 3)
              <span class="material-symbols-outlined" style="font-size:14px">check</span>
            @else
              3
            @endif
          </div>
          <span class="text-sm font-medium {{ $currentStep === 3 ? 'text-slate-800' : 'text-slate-400' }} hidden sm:inline">Email Alerts</span>
        </div>

      </div>
    </div>
  </div>

  {{-- ── Page content ────────────────────────────────────────────── --}}
  <main class="max-w-2xl mx-auto px-6 py-10">
    @yield('content')
  </main>

</body>
</html>
