@extends('layouts.setup')

@section('title', 'Email Alerts')

@php $currentStep = 3; @endphp

@section('content')

<div class="mb-8">
  <h1 class="font-display font-bold text-3xl text-slate-900 mb-2">Enable email alerts</h1>
  <p class="text-slate-500">Get notified by email when humidity spikes, temperature goes out of range, or your silica gel needs replacing.</p>
</div>

@if($hasGmail)
{{-- Already connected --}}
<div class="bg-white border border-emerald-200 rounded-2xl p-6 mb-6">
  <div class="flex items-center gap-4">
    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
      <span class="material-symbols-outlined text-emerald-600">mark_email_read</span>
    </div>
    <div>
      <p class="font-semibold text-slate-800">Gmail is connected</p>
      <p class="text-sm text-slate-500 mt-0.5">Alert emails will be sent from your Google account. You can add more recipients in Settings.</p>
    </div>
  </div>
</div>
@else
{{-- Not connected --}}
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-6">
  <div class="p-6">
    <div class="flex items-start gap-4 mb-6">
      <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-blue-600">mail</span>
      </div>
      <div>
        <p class="font-semibold text-slate-800 mb-1">Connect your Gmail account</p>
        <p class="text-sm text-slate-500">DryBox AI uses your own Gmail to send alerts — no SMTP config needed. The alert email is sent from your own address.</p>
      </div>
    </div>

    {{-- What you'll get --}}
    <div class="space-y-2.5 mb-6">
      @foreach([
        ['crisis_alert',  'Critical humidity alert',         'When humidity exceeds your critical threshold.'],
        ['warning_amber', 'Warning humidity alert',          'When humidity enters the warning zone.'],
        ['thermostat',    'Temperature out of range',        'If temp drops below min or exceeds max.'],
        ['science',       'Silica gel replacement reminder', 'Based on your replacement interval.'],
        ['security',      'Tamper / door alert',             'When protection mode is armed and the door opens.'],
      ] as [$icon, $label, $desc])
      <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-blue-500 mt-0.5" style="font-size:18px">{{ $icon }}</span>
        <div>
          <p class="text-sm font-medium text-slate-700">{{ $label }}</p>
          <p class="text-xs text-slate-400">{{ $desc }}</p>
        </div>
      </div>
      @endforeach
    </div>

    <a
      href="{{ route('auth.google.redirect') }}"
      class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
    >
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Connect Gmail
    </a>
  </div>
</div>
@endif

{{-- What the alert email looks like --}}
<div class="bg-white border border-slate-200 rounded-2xl p-5 mb-8">
  <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Example alert email</p>
  <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 text-sm">
    <p class="text-slate-500 text-xs mb-3 font-mono">From: you@gmail.com · To: {{ auth()->user()->email }}</p>
    <p class="font-bold text-slate-800 mb-1">🚨 DryBox AI — Critical Humidity Alert</p>
    <p class="text-slate-600 text-xs leading-relaxed">
      <strong>{{ $device->name }}</strong> has reached critical humidity.<br>
      Current reading: <strong>48.2%</strong> (threshold: 45%)<br>
      <span class="text-slate-400">Recorded at {{ now()->format('d M Y, H:i') }}</span>
    </p>
  </div>
</div>

{{-- Buttons --}}
<div class="flex flex-col sm:flex-row gap-3">
  <a
    href="{{ route('dashboard') }}"
    class="flex-1 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
  >
    <span class="material-symbols-outlined" style="font-size:18px">dashboard</span>
    Go to Dashboard
  </a>
  @if(!$hasGmail)
  <a
    href="{{ route('dashboard') }}"
    class="px-6 py-3.5 border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl font-semibold text-sm transition-colors flex items-center justify-center"
  >
    Skip for now
  </a>
  @endif
</div>

@endsection
