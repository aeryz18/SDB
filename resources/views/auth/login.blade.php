<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — DryBox AI</title>
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
    <h1 class="font-display font-bold text-3xl text-white mb-2">Welcome back</h1>
    <p class="text-slate-400 text-sm">Sign in to your monitoring dashboard</p>
  </div>

  {{-- Card --}}
  <div class="glass rounded-2xl p-8 shadow-2xl shadow-black/50">

    {{-- Session/Error message --}}
    @if ($errors->any())
      <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 flex items-start gap-3">
        <span class="material-symbols-outlined text-red-400 text-base mt-0.5">error</span>
        <p class="text-red-300 text-sm">{{ $errors->first() }}</p>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
      @csrf

      {{-- Email --}}
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500" style="font-size:18px">mail</span>
          <input
            id="email" name="email" type="email"
            value="{{ old('email') }}"
            required autofocus autocomplete="email"
            autocapitalize="none" autocorrect="off" spellcheck="false"
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
            required autocomplete="current-password"
            placeholder="••••••••"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          >
        </div>
      </div>

      {{-- Remember --}}
      <div class="flex items-center gap-2">
        <input id="remember" name="remember" type="checkbox" class="w-4 h-4 rounded accent-blue-500">
        <label for="remember" class="text-sm text-slate-400 cursor-pointer">Keep me signed in</label>
      </div>

      {{-- Submit --}}
      <button type="submit" class="btn-primary w-full py-3.5 rounded-xl font-semibold text-white text-sm mt-2 flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-base">login</span>
        Sign In to Dashboard
      </button>
    </form>

    {{-- Divider --}}
    <div class="flex items-center gap-3 my-6">
      <div class="flex-1 h-px bg-white/10"></div>
      <span class="text-xs text-slate-500">or</span>
      <div class="flex-1 h-px bg-white/10"></div>
    </div>

    {{-- Google OAuth --}}
    <a href="{{ route('auth.google.redirect') }}"
       class="w-full flex items-center justify-center gap-3 py-3.5 rounded-xl font-semibold text-sm text-white border border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20 transition-all duration-200">
      <svg viewBox="0 0 24 24" class="w-5 h-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Sign in with Google
    </a>
  </div>

  {{-- Register link --}}
  <p class="text-center text-slate-500 text-sm mt-6">
    Don't have an account?
    <a href="{{ route('register') }}" class="text-blue-400 font-semibold hover:text-blue-300 transition-colors">Create one free</a>
  </p>
  <p class="text-center mt-3">
    <a href="{{ route('landing') }}" class="text-slate-600 text-xs hover:text-slate-400 transition-colors">← Back to home</a>
  </p>
</div>

</body>
</html>
