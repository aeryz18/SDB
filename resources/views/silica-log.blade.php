@extends('layouts.app')

@section('title', 'Silica Gel Log | DryBox AI')

@section('content')
<div class="max-w-5xl mx-auto space-y-md">

    {{-- ── Header ────────────────────────────────────────────────────── --}}
    <div>
        <h1 class="font-display-lg text-display-lg text-primary">Silica Gel Log</h1>
        <p class="font-body-base text-on-surface-variant mt-1">
            Replacement history, manual scheduling, and AI-assisted replacement suggestions.
        </p>
    </div>

    @if(!$device)
    <div class="col-span-full py-16 flex flex-col items-center justify-center text-slate-400 bg-white border border-outline-variant rounded-xl">
        <span class="material-symbols-outlined text-5xl mb-3 text-slate-300">science</span>
        <p class="font-semibold text-slate-500 mb-1">No unit registered yet</p>
        <p class="text-sm">Add your DryBox unit on the Device page to start tracking silica gel replacement.</p>
    </div>
    @else

    {{-- ── Status Card ───────────────────────────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-primary" style="font-size:20px">science</span>
                    <span class="font-label-caps text-label-caps text-on-surface-variant">SILICA GEL STATUS</span>
                </div>

                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    @if(!$silica['replaced'] && $silica['source'] === 'unset')
                        <span class="font-headline-md text-2xl font-bold text-slate-400">Unknown</span>
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-slate-50 text-slate-500 border border-slate-200">Not logged</span>
                    @elseif($silica['due'])
                        <span class="font-headline-md text-2xl font-bold text-red-600">Overdue</span>
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-red-50 text-red-700 border border-red-200">Replace now</span>
                    @elseif($silica['warning'])
                        <span class="font-headline-md text-2xl font-bold text-amber-600">{{ $silica['days_left'] }}d left</span>
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Replace soon</span>
                    @else
                        <span class="font-headline-md text-2xl font-bold text-emerald-600">{{ $silica['days_left'] }}d left</span>
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">OK</span>
                    @endif
                </div>

                <p class="text-xs text-slate-400 mt-2">
                    @if($silica['source'] === 'manual')
                        Next replacement: <span class="font-semibold text-slate-600">{{ $silica['due_date']->format('d M Y') }}</span> (manually set)
                    @elseif($silica['source'] === 'interval')
                        Next replacement: <span class="font-semibold text-slate-600">{{ $silica['due_date']->format('d M Y') }}</span> (estimated from a {{ $silica['interval'] }}-day interval)
                    @else
                        No replacement date set yet.
                    @endif
                    — reminder {{ $silica['notify_days_before'] }} day{{ $silica['notify_days_before'] === 1 ? '' : 's' }} before
                </p>

                @if($silica['replaced'])
                <div class="mt-4 max-w-md">
                    <div class="flex justify-between text-xs text-slate-400 mb-1.5">
                        <span>Gel life used</span>
                        <span>{{ $silica['days_since'] }}d / {{ $silica['interval'] }}d</span>
                    </div>
                    <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $silica['due'] ? 'bg-red-500' : ($silica['warning'] ? 'bg-amber-500' : 'bg-emerald-500') }} rounded-full transition-all duration-700"
                             style="width:{{ $silica['bar_pct'] }}%"></div>
                    </div>
                </div>
                @endif

                @if($silicaAvgLifespan !== null)
                <p class="text-xs text-slate-400 mt-3">
                    Avg. gel lifespan: <span class="font-semibold text-slate-600">{{ $silicaAvgLifespan }}d</span>
                </p>
                @endif
            </div>

            <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-start gap-3 md:gap-2">
                <div class="p-4 {{ $silica['due'] ? 'bg-red-50' : ($silica['warning'] ? 'bg-amber-50' : 'bg-emerald-50') }} rounded-xl">
                    <span class="material-symbols-outlined {{ $silica['due'] ? 'text-red-500' : ($silica['warning'] ? 'text-amber-500' : 'text-emerald-500') }}" style="font-size:32px">science</span>
                </div>
                <button
                    onclick="openReplaceModal()"
                    id="silica-btn"
                    class="text-sm px-4 py-2 rounded-lg font-semibold {{ $silica['due'] ? 'bg-red-50 text-red-600 border border-red-200 hover:bg-red-100' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100' }} transition-colors whitespace-nowrap">
                    Replace Silica Gel
                </button>
                <span class="text-[10px] text-slate-400 text-center md:text-right">Use only after you've physically swapped the gel</span>
            </div>
        </div>
    </section>

    {{-- ── Edit the replacement date/reminder (no new replacement logged) ── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-6">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-1">Update Replacement Date</h2>
        <p class="text-sm text-on-surface-variant mb-4">
            Just edits the date and reminder shown above — the gel itself hasn't been replaced yet, so nothing is added to the history below. Use <strong>Replace Silica Gel</strong> instead once you've actually swapped it.
        </p>
        <form id="next-replacement-form" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end flex-wrap" onsubmit="return false;">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="next-replacement-date">New target date</label>
                <input
                    type="date"
                    id="next-replacement-date"
                    value="{{ $silica['source'] === 'manual' ? $silica['due_date']->format('Y-m-d') : '' }}"
                    class="px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                <p class="text-[11px] text-slate-400 mt-1 max-w-[220px]">Or click "Clear Custom Date" below to go back to the automatic {{ $silica['interval'] }}-day estimate.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="next-replacement-notify">Remind me this many days before</label>
                <input
                    type="number"
                    id="next-replacement-notify"
                    min="1" max="365"
                    value="{{ $silica['notify_days_before'] }}"
                    class="w-28 px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="saveNextReplacementDate({{ $device->id }})" id="save-date-btn"
                        class="px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-colors">
                    Save Plan
                </button>
                @if($silica['source'] === 'manual')
                <button type="button" onclick="clearNextReplacementDate({{ $device->id }})" id="clear-date-btn"
                        title="Removes your custom date and switches back to the automatic {{ $silica['interval'] }}-day estimate"
                        class="px-4 py-2.5 border border-outline-variant text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
                    Clear Custom Date
                </button>
                @endif
            </div>
        </form>
    </section>

    {{-- ── Replacement History ──────────────────────────────────────── --}}
    <section class="bg-white border border-outline-variant rounded-xl p-6">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-4">Replacement History</h2>
        @forelse($silicaHistory as $entry)
        <div class="flex justify-between text-sm py-3 border-b border-slate-100 last:border-0">
            <span class="text-slate-700 font-medium">{{ $entry->replaced_at->format('d M Y') }}</span>
            <span class="text-slate-400">
                {{ $entry->interval_days_actual !== null ? $entry->interval_days_actual . 'd since previous' : 'first recorded replacement' }}
            </span>
        </div>
        @empty
        <p class="text-sm text-slate-400 py-2">No replacements logged yet.</p>
        @endforelse

        @if($silicaHistory instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-4">
            {{ $silicaHistory->links() }}
        </div>
        @endif
    </section>

    {{-- ── Replace Silica Gel Modal ─────────────────────────────────── --}}
    <div id="replace-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeReplaceModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10">
            <div class="p-8">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="font-display-lg text-xl font-bold text-primary">Replace Silica Gel</h2>
                    <button onclick="closeReplaceModal()" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-5">
                    This marks the gel as replaced right now. Set when you plan to replace it next, and when you'd like a reminder.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="replace-next-date">Next replacement date</label>
                        <input
                            type="date"
                            id="replace-next-date"
                            required
                            class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                    </div>

                    <div>
                        <button type="button" onclick="askAiInModal({{ $device->id }})" id="replace-ai-btn"
                                class="text-xs px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 font-semibold hover:bg-blue-50 transition-colors inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined" style="font-size:16px">auto_awesome</span>
                            Ask AI for a suggestion
                        </button>

                        <div id="replace-ai-box" class="hidden mt-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-900">
                            <p class="font-semibold" id="replace-ai-date"></p>
                            <p class="mt-1 text-blue-800" id="replace-ai-reasoning"></p>
                            <button type="button" onclick="useAiSuggestion()"
                                    class="mt-2 text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition-colors">
                                Use this date
                            </button>
                        </div>
                        <div id="replace-ai-error" class="hidden mt-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="replace-notify-days">Notify me this many days before</label>
                        <input
                            type="number"
                            id="replace-notify-days"
                            min="1" max="365"
                            required
                            class="w-full px-4 py-2.5 border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors">
                    </div>
                </div>

                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeReplaceModal()"
                            class="flex-1 py-2.5 border border-outline-variant text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
                        Cancel
                    </button>
                    <button type="button" onclick="submitReplace({{ $device->id }})" id="replace-submit-btn"
                            class="flex-1 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-colors">
                        Replace Silica Gel
                    </button>
                </div>
            </div>
        </div>
    </div>

    @endif
</div>
@endsection

@if($device)
@section('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const DEFAULT_NEXT_DATE = '{{ now()->addDays($silica['interval'])->format('Y-m-d') }}';
const DEFAULT_NOTIFY_DAYS = {{ $silica['notify_days_before'] }};

// ── Replace Silica Gel modal ──────────────────────────────────────────
let lastAiSuggestedDate = null;
let silicaReloadTimer = null;

function openReplaceModal() {
    const dateInput   = document.getElementById('replace-next-date');
    const notifyInput = document.getElementById('replace-notify-days');
    dateInput.value   = DEFAULT_NEXT_DATE;
    notifyInput.value = DEFAULT_NOTIFY_DAYS;
    lastAiSuggestedDate = null;
    document.getElementById('replace-ai-box').classList.add('hidden');
    document.getElementById('replace-ai-error').classList.add('hidden');
    document.getElementById('replace-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReplaceModal() {
    document.getElementById('replace-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function askAiInModal(deviceId) {
    const btn    = document.getElementById('replace-ai-btn');
    const box    = document.getElementById('replace-ai-box');
    const errBox = document.getElementById('replace-ai-error');

    box.classList.add('hidden');
    errBox.classList.add('hidden');
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin" style="font-size:16px">progress_activity</span> Asking AI…';

    try {
        const resp = await fetch(`/devices/${deviceId}/silica-ai-suggestion`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const json = await resp.json();

        if (!resp.ok || !json.success) throw new Error(json.message || 'AI suggestion is temporarily unavailable.');

        lastAiSuggestedDate = json.suggested_date;
        document.getElementById('replace-ai-date').textContent = `Suggested: ${json.suggested_date}`;
        document.getElementById('replace-ai-reasoning').textContent = json.reasoning ?? '';
        box.classList.remove('hidden');
    } catch (e) {
        errBox.textContent = e.message || 'AI suggestion is temporarily unavailable. Please try again in a moment.';
        errBox.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

function useAiSuggestion() {
    if (lastAiSuggestedDate) {
        document.getElementById('replace-next-date').value = lastAiSuggestedDate;
    }
}

async function submitReplace(deviceId) {
    const dateInput   = document.getElementById('replace-next-date');
    const notifyInput = document.getElementById('replace-notify-days');

    if (!dateInput.value) { alert('Pick the next replacement date first.'); return; }
    if (!notifyInput.value || notifyInput.value < 1) { alert('Enter how many days before to notify you.'); return; }

    const confirmed = confirm(
        `Mark silica gel as replaced now?\n\n` +
        `Next replacement: ${dateInput.value}\n` +
        `Notify: ${notifyInput.value} day(s) before`
    );
    if (!confirmed) return;

    const btn = document.getElementById('replace-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const resp = await fetch(`/devices/${deviceId}/silica`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                next_replacement_at: dateInput.value,
                notify_days_before:  notifyInput.value,
            }),
        });

        if (!resp.ok) {
            const errJson = await resp.json().catch(() => null);
            throw new Error(errJson?.message || 'request failed');
        }
        const json = await resp.json();

        closeReplaceModal();
        showSilicaToast('Silica gel marked as replaced.', () => undoSilicaReplacement(deviceId, json.replacement_id));
    } catch {
        btn.disabled = false;
        btn.textContent = 'Replace Silica Gel';
        alert('Something went wrong marking the silica gel as replaced — please try again.');
    }
}

function showSilicaToast(message, undoFn) {
    document.getElementById('silica-toast')?.remove();
    clearTimeout(silicaReloadTimer);

    const t = document.createElement('div');
    t.id = 'silica-toast';
    t.className = 'fixed bottom-24 right-6 z-50 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold flex items-center gap-3 transition-all';
    t.style.cssText = 'background:#1e293b;color:white;';
    t.innerHTML = `
        <span class="material-symbols-outlined text-emerald-400" style="font-size:18px">check_circle</span>
        <span>${message}</span>
        <button class="text-blue-300 hover:text-blue-100 underline" id="silica-toast-undo">Undo</button>
    `;
    document.body.appendChild(t);

    document.getElementById('silica-toast-undo').onclick = async () => {
        clearTimeout(silicaReloadTimer);
        t.remove();
        await undoFn();
        location.reload();
    };

    silicaReloadTimer = setTimeout(() => {
        t.remove();
        location.reload();
    }, 8000);
}

async function undoSilicaReplacement(deviceId, replacementId) {
    if (!replacementId) return;
    try {
        await fetch(`/devices/${deviceId}/silica/${replacementId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
    } catch { /* best effort — page reload below will simply show the pre-undo state */ }
}

// ── Adjust next-replacement plan (standalone, no fresh replacement) ────
async function saveNextReplacementDate(deviceId) {
    const input       = document.getElementById('next-replacement-date');
    const notifyInput = document.getElementById('next-replacement-notify');
    const btn         = document.getElementById('save-date-btn');
    if (!input.value) { alert('Pick a date first, or use "Revert to automatic" to clear it.'); return; }

    btn.disabled = true;
    try {
        const resp = await fetch(`/devices/${deviceId}/silica-next-replacement`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ next_replacement_at: input.value, notify_days_before: notifyInput.value || null }),
        });
        if (!resp.ok) throw new Error('request failed');
        location.reload();
    } catch {
        btn.disabled = false;
        alert('Something went wrong saving the date — please try again.');
    }
}

async function clearNextReplacementDate(deviceId) {
    const btn = document.getElementById('clear-date-btn');
    btn.disabled = true;
    try {
        const resp = await fetch(`/devices/${deviceId}/silica-next-replacement`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ next_replacement_at: null }),
        });
        if (!resp.ok) throw new Error('request failed');
        location.reload();
    } catch {
        btn.disabled = false;
        alert('Something went wrong clearing the date — please try again.');
    }
}

// ── ESC to close the modal ──────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeReplaceModal();
});
</script>
@endsection
@endif
