<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — DryBox AI</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0..1&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #080d1a; }
    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.09); }
    .btn-primary { background: linear-gradient(135deg,#2b5bb5,#0061a4); transition: all .2s; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(43,91,181,.4); }
    .glow { background: radial-gradient(ellipse at 50% 0%, rgba(43,91,181,.2) 0%, transparent 65%); }
    .input-field { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; transition: border-color .2s; }
    .input-field::placeholder { color: rgba(148,163,184,.5); }
    .input-field:focus { outline: none; border-color: rgba(96,165,250,.5); background: rgba(255,255,255,0.07); }
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
  </style>
</head>
<body class="glow min-h-screen flex items-center justify-center px-4 py-12">

<div class="w-full max-w-md">
  {{-- Logo --}}
  <div class="text-center mb-8">
    <a href="{{ route('landing') }}" class="inline-flex items-center gap-2.5 mb-6">
      <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
        <span class="material-symbols-outlined text-white">humidity_indoor</span>
      </div>
      <span class="font-display font-bold text-2xl text-white tracking-tight">DryBox <span class="text-blue-400">AI</span></span>
    </a>
    <h1 class="font-display font-bold text-3xl text-white mb-2">Create your account</h1>
    <p class="text-slate-400 text-sm">Start monitoring your sensor for free</p>
  </div>

  {{-- Card --}}
  <div class="glass rounded-2xl p-8 shadow-2xl shadow-black/50">

    {{-- Errors --}}
    @if ($errors->any())
      <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20">
        <ul class="space-y-1">
          @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2 text-red-300 text-sm">
              <span class="material-symbols-outlined text-red-400 text-base mt-0.5">error</span>
              {{ $error }}
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
      @csrf

      {{-- Name --}}
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Full Name</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500" style="font-size:18px">person</span>
          <input
            id="name" name="name" type="text"
            value="{{ old('name') }}"
            required autofocus autocomplete="name"
            placeholder="Alex Thompson"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          >
        </div>
      </div>

      {{-- Email --}}
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500" style="font-size:18px">mail</span>
          <input
            id="email" name="email" type="email"
            value="{{ old('email') }}"
            required autocomplete="email"
            placeholder="you@example.com"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          >
        </div>
      </div>

      {{-- Password --}}
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500" style="font-size:18px">lock</span>
          <input
            id="password" name="password" type="password"
            required autocomplete="new-password"
            placeholder="Min. 8 characters"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          >
        </div>
      </div>

      {{-- Confirm Password --}}
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Confirm Password</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500" style="font-size:18px">lock_reset</span>
          <input
            id="password_confirmation" name="password_confirmation" type="password"
            required autocomplete="new-password"
            placeholder="Repeat your password"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          >
        </div>
      </div>

      {{-- Submit --}}
      <button type="submit" class="btn-primary w-full py-3.5 rounded-xl font-semibold text-white text-sm mt-2 flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-base">person_add</span>
        Create Account & Enter Dashboard
      </button>
    </form>
  </div>

  {{-- Login link --}}
  <p class="text-center text-slate-500 text-sm mt-6">
    Already have an account?
    <a href="{{ route('login') }}" class="text-blue-400 font-semibold hover:text-blue-300 transition-colors">Sign in</a>
  </p>
  <p class="text-center mt-3">
    <a href="{{ route('landing') }}" class="text-slate-600 text-xs hover:text-slate-400 transition-colors">← Back to home</a>
  </p>
</div>

</body>
</html>
