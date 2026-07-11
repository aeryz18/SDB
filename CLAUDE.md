# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**DryBox AI** — a Laravel 13 IoT monitoring dashboard for smart dry storage units. The system has a dual data flow: the browser reads Firebase directly for live sensor values, while a Laravel scheduler polls Firebase server-side every minute to persist history, evaluate alert rules, and send Gmail notifications.

## Development Commands

```bash
# First-time setup (install, .env, key, migrate, npm build)
composer setup

# Install dependencies only
composer install && npm install

# Start all dev servers (Laravel + queue worker + Vite HMR + scheduler)
composer dev

# Run tests
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Code style (Laravel Pint)
./vendor/bin/pint

# Manually trigger one poll cycle (useful for testing alert rules)
php artisan drybox:poll

# Seed the default device for the first user
php artisan db:seed --class=DeviceSeeder
```

`composer dev` runs four processes concurrently via `npx concurrently`: `php artisan serve`, `queue:listen --tries=1 --timeout=0`, `npm run dev`, and `schedule:work`. Laravel Pail is **not** included — it requires the `pcntl` extension which is unavailable on Windows.

## Environment Setup

Required `.env` values beyond the defaults:

```
APP_URL=http://localhost:8000          # Must be localhost for Google OAuth redirect

DB_CONNECTION=mysql
DB_DATABASE=drybox_ai

FIREBASE_API_KEY=...                   # Browser JS SDK (client-side)
FIREBASE_DATABASE_URL=https://your-project-default-rtdb.firebaseio.com/
FIREBASE_CREDENTIALS=storage/app/firebase/credentials.json   # Server-side kreait SDK

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...

GEMINI_API_KEY=...                     # Google AI Studio (aistudio.google.com) — silica AI suggestion
GEMINI_MODEL=gemini-2.5-flash          # optional, defaults to gemini-2.5-flash
```

The Firebase service-account JSON (`storage/app/firebase/credentials.json`) is required for the scheduler to read Firebase server-side. Without it, all browser-facing Firebase features still work, but `drybox:poll` will error. The file is gitignored via `storage/app/firebase/.gitignore`.

`GOOGLE_REDIRECT_URI` must **not** be set in `.env` — the redirect is derived from `APP_URL` in `config/services.php` to avoid Windows CRLF whitespace errors.

Without `GEMINI_API_KEY`, all other features work normally — only the "Ask AI" button on the Silica Log page returns a friendly error.

## Architecture

### Dual Data Flow

```
Browser (Firebase JS SDK v9 compat, CDN)
  └─ db.ref(device.firebase_path).on('value', ...) → live UI updates

Laravel Scheduler (drybox:poll, every minute)
  └─ FirebaseReader (kreait) → reads Firebase server-side
  └─ stores Reading row → evaluates AlertEvaluator + silica gel due-date/drift checks
  └─ creates Alert rows → dispatches SendGmailAlert job
  └─ queue worker → GmailApiSender (user's own Gmail via refresh token)
```

There is no `routes/api.php`. All device mutation routes (`/devices/*`, `/device`, `/report`) are standard web routes returning redirects or file downloads.

### Database (MySQL `drybox_ai`)

Five application tables beyond the Laravel defaults:

| Table | Purpose |
|-------|---------|
| `devices` | Registry of physical boxes — `user_id`, `name`, `location`, `firebase_path` (unique), `is_active` |
| `device_settings` | Per-device config — `warn_humidity` (35), `crit_humidity` (45), `temp_min`/`temp_max` (nullable), `silica_last_replaced_at`, `silica_interval_days` (90), `silica_next_replacement_at` (nullable manual override — takes precedence over the interval when set), `silica_notify_days_before` (7 — the single per-device lead time that drives both the in-app warning state and the `silica_upcoming` email), `protection_mode`, `door_field`, `notify_emails` (JSON), `alert_cooldown_minutes` (30) |
| `readings` | Sensor history — `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at`; **no** `created_at`/`updated_at` (`$timestamps = false`) |
| `alerts` | Alert log — `device_id`, `type` enum (`humidity_warn`, `humidity_crit`, `temp`, `silica_due`, `silica_drift`, `silica_upcoming`, `tamper`), `message`, `value`, `emailed_at`, `resolved_at`; composite index on `(device_id, type, resolved_at)`. `humidity_warn`/`humidity_crit` remain valid enum values for historical rows but are no longer generated — real-time humidity is surfaced on the live dashboard instead of by email. (`fungus` was a historical enum value for a retired feature — unlike `humidity_warn`/`humidity_crit`, it was fully removed, including deleting any existing rows, since the feature was deliberately dropped with no trace wanted, not just phased out.) |
| `silica_replacements` | Replacement history log — `device_id`, `replaced_at`, `interval_days_actual` (nullable — null for the first-ever replacement on a device) |

### Models

All models use **PHP 8.3 attribute syntax**: `#[Fillable([...])]` and `#[Hidden([...])]` instead of `$fillable`/`$hidden` array properties.

`User` → `hasMany(Device)` → `hasOne(DeviceSetting)` + `hasMany(Reading)` + `hasMany(Alert)` + `hasMany(SilicaReplacement)`

`User::google_refresh_token` uses `'encrypted'` cast. `Reading::$timestamps = false`. `DeviceSetting::silica_next_replacement_at` casts to `'date'` (not `'datetime'` — the UI control is a date input, no time-of-day precision).

### Controllers

- **`DryBoxController`** — page controllers; each method queries the user's primary active device (ordered by `created_at`) and passes `$device`, `$settings`, `$silica`, etc. to the view. `dashboard()`, `device()` (the combined Equipment+Settings page — device card/live readings/protection toggle plus thresholds/Firebase info/profile; merged into one page and nav entry since the app only ever supports a single device), `silicaLog()` (the dedicated Silica Log page — full status/history/manual-date/AI-suggestion UI), `analytics()`. `saveSettings()` handles `POST /device` — it only validates and saves `warn_humidity`, `crit_humidity`, `silica_interval_days`, and `notify_emails`; other settings (`temp_min`/`temp_max`, `protection_mode`, silica replacement/override/AI-suggestion) are mutated via AJAX through `DeviceController`.
- **`AuthController`** — email/password auth + Google OAuth (`redirectToGoogle`, `handleGoogleCallback`). Callback finds-or-creates user by `google_id`, stores encrypted refresh token.
- **`DeviceController`** — `store`, `destroy`, `toggleProtection`; plus the silica-specific set: `markSilicaReplaced` (the "Replace Silica Gel" modal's submit target — **requires** `next_replacement_at` and `notify_days_before` in the request, logs a `SilicaReplacement` row, sets both fields, resolves outstanding silica alerts), `undoSilicaReplacement` (undoes only the most-recent replacement, 403/409-gated, also reverts `silica_next_replacement_at` to null), `updateSilicaNextReplacementAt` (standalone "adjust the plan without a fresh replacement" form — both fields optional/nullable here, resolves outstanding `silica_due`/`silica_upcoming` alerts so the once-per-cycle email guard re-arms), `getSilicaAiSuggestion` (calls `GeminiSilicaAdvisor`, returns a 502 JSON error on failure rather than throwing). All return JSON for AJAX calls.
- **`ReportController`** — `generate()` streams a CSV download built by `ReportGenerator` (which uses `->cursor()` for readings and pre-computed aggregate stats via `selectRaw`).
- **`DemoController`** — sets/clears a `demo_mode` session flag, returns demo Blade views.

### Services

- **`FirebaseReader`** (`app/Services/Firebase/`) — thin wrapper over kreait RTDB: `read(string $path): array`.
- **`AlertEvaluator`** — `evaluate(Device, Reading): array` checks temperature (`temp_min`/`temp_max`) and door tamper (no humidity threshold alerts — see below). Checks cooldown via `alerts` table.
- **`SilicaStatus`** — `evaluate(?DeviceSetting $settings): array` is the single source of truth for silica due-date/warning/overdue state, used by the dashboard, Device page, Silica Log page, and `PollDeviceData`. Resolves a due date (manual `silica_next_replacement_at` override wins when set, else `silica_last_replaced_at + silica_interval_days`), and returns `due`/`warning` (`warning` is `!due && days_left <= $settings->silica_notify_days_before` — a single per-device value, not a hardcoded constant; see "Silica Gel Replacement" below), `days_left`, `notify_days_before`, `days_since`/`bar_pct` (always interval-derived, for the progress bar), `due_date`, and `source` (`'manual'`/`'interval'`/`'unset'`).
- **`GeminiSilicaAdvisor`** — `suggest(Device): array` calls the Gemini API (`generativelanguage.googleapis.com`, REST, via the `Http` facade) with a prompt built from the device's `SilicaReplacement` history and aggregated recent sensor stats, asking for a suggested next replacement date + brief reasoning as JSON. On-demand only — triggered from inside the "Replace Silica Gel" modal on the Silica Log page ("Ask AI for a suggestion" → "Use this date" one-click fills the date field, or the user can ignore it and pick their own) — no scheduled job, no persistence.
- **`ReportGenerator`** — `generate(Device, Carbon $from, Carbon $to): string` builds the CSV condition report (readings, alerts, summary stats); `filename(...)` builds the matching download name. Shared by `ReportController::generate()` (on-demand download) and `SendMonthlyReport` (emailed attachment).
- **`GmailApiSender`** — sends email via Gmail API using the user's OAuth refresh token (no SMTP). Exchanges refresh token for access token, encodes MIME as `base64url`, POSTs to `gmail.googleapis.com`. `send()` sends a plain HTML message; `sendWithAttachment()` builds a `multipart/mixed` MIME message with a base64-encoded file part (used for the monthly report CSV).

### Scheduler & Queue

`drybox:poll` runs every minute (registered in `routes/console.php`). For each active device it:
1. Reads Firebase via kreait
2. Stores a `Reading`
3. Runs `AlertEvaluator::evaluate()` (temp/tamper — no humidity check)
4. Silica gel checks — see the "Silica Gel Replacement" section below for the full threshold breakdown; briefly: `silica_upcoming`/`silica_due` alerts fire **once per replacement cycle** (guarded on an unresolved alert of that type already existing, not on "already today"), and the humidity-drift check (`PollDeviceData::checkSilicaDrift()`) is unchanged — still daily-guarded, comparing the closed-door humidity average from the first `SILICA_BASELINE_WINDOW_DAYS` (3) after `silica_last_replaced_at` against the closed-door average over the last `SILICA_RECENT_WINDOW_HOURS` (24), firing `silica_drift` if it has risen by `SILICA_DRIFT_RH_THRESHOLD` (10 points) or more, provided both windows have at least `SILICA_MIN_SAMPLES` (10) closed-door readings.
5. Creates `Alert` rows and dispatches `SendGmailAlert` jobs

`SendGmailAlert` uses `QUEUE_CONNECTION=database`. On `invalid_grant` from Gmail it clears `google_refresh_token` rather than retrying.

`drybox:monthly-report` runs on the 1st of each month at 01:00 (registered in `routes/console.php`). For each active device it dispatches a `SendMonthlyReport` job per `notify_emails` recipient, covering the previous calendar month. The job builds the CSV via `ReportGenerator` and emails it as an attachment via `GmailApiSender::sendWithAttachment()` using `resources/views/emails/monthly-report.blade.php`. This runs alongside the on-demand `/report` download, not instead of it. Both `SendGmailAlert` and `SendMonthlyReport` clear `google_refresh_token` on `invalid_grant`.

### Silica Gel Replacement

Full detail in `docs/silica-gel-replacement.md`. There is a **single** notify threshold now — `device_settings.silica_notify_days_before` (default 7) — set per-device via the Replace form or the standalone adjustment form, not a hardcoded constant. It drives both the in-app warning state (status badge color, dashboard login banner) and the once-per-cycle `silica_upcoming` email; the overdue `silica_due` email fires separately once `SilicaStatus::evaluate()`'s `due` flag is true (i.e. `days_left <= 0`). Earlier versions of this feature had two separate hardcoded thresholds (3-day UI / 7-day email) — that split was intentionally removed in favor of one user-configurable value; don't reintroduce the split.

**Replace Silica Gel flow**: clicking "Replace Silica Gel" on the Silica Log page opens a modal — not an immediate action. The modal requires a next-replacement date (optionally filled via the "Ask AI for a suggestion" → "Use this date" one-click, or picked manually — the AI suggestion is never applied automatically) and a notify-days-before value, then asks for confirmation (`confirm()`) before submitting. Submission is a single `POST /devices/{device}/silica` carrying both fields — `markSilicaReplaced()` validates them as required and sets `silica_last_replaced_at`, `silica_next_replacement_at`, and `silica_notify_days_before` together. A separate "Adjust Next Replacement Plan" form on the same page lets the user change the date/lead-time later without pretending a fresh physical replacement happened (both fields optional there).

The dashboard login banner is plain server-rendered Blade (`$silica['warning'] || $silica['due']` in `dashboard.blade.php`) — no JS, no session flag — so it re-evaluates on every page load and keeps appearing until the gel is marked replaced. The Silica Log page (`/silica-log`, nav entry "Silica Log") is the dedicated page for the full status card, the Replace modal, the adjustment form, replacement history, and the Gemini suggestion — the Device page keeps only a compact per-device indicator + a link to this page (its own "Mark Replaced" AJAX flow was removed in favor of linking here).

**Note:** there was previously a "fungus risk" feature (`FungusRisk` service, a dashboard risk gauge, `fungus_alerts_enabled` setting) that scored mould-growth risk from humidity/temperature/door-event statistics. It has been **completely removed** (code, DB columns/enum value, docs) per product decision — do not reintroduce it or reference it; if you see "fungus" anywhere in this codebase going forward, it's a bug, not an intentional feature.

### Google OAuth

Scopes requested: `openid email profile https://www.googleapis.com/auth/gmail.send` with `access_type=offline&prompt=consent`. The redirect URI **must be** `http://localhost:8000/auth/google/callback` — Google does not accept `.test` domains. Google Cloud Console requires the Gmail API enabled and the user added as an OAuth test user while the app is in "Testing" mode.

### Views & Layouts

Two layouts in `resources/views/layouts/`:
- `app.blade.php` — authenticated shell (topbar, sidebar, mobile bottom nav)
- `demo.blade.php` — identical plus "Demo Mode" banner and guided `DemoTour` JS class

Views use `@yield('content')` and `@yield('scripts')`. Firebase init and all live-data JS lives in `@section('scripts')` of each view. Demo views (`resources/views/demo/`) replace Firebase calls with `setInterval`-based simulation. The demo layout has its own hardcoded nav (not the `$navItems`/`$mobileNav` array used by `layouts/app.blade.php`) — the Silica Log page does not currently have a demo-mode equivalent.

### Tailwind CSS

Loaded from CDN (`cdn.tailwindcss.com`) and configured via an inline `<script id="tailwind-config">` in both layout files — **not** via `tailwind.config.js` or Vite. The `@tailwindcss/vite` package is present but unused. Style changes must be applied to the inline config in **both** `layouts/app.blade.php` and `layouts/demo.blade.php`.

Design tokens: Material Design 3 color names as custom Tailwind classes, Space Grotesk for display/headlines, Inter for body. Icons: Google Material Symbols Outlined from CDN.

### Alert Thresholds

Thresholds (`warn_humidity`, `crit_humidity`) are stored in `device_settings` and passed server-side to every view. The Device page also writes them to `localStorage` (`warnThreshold`, `critThreshold`) as a backward-compat shim for its own live JS. Do not add new localStorage threshold reads — use the server-injected values.

### Blade + JS Gotcha

Never pass a multi-line `fn()` with nested parentheses directly to `@json()`. The Blade compiler counts `()` to find the directive boundary and breaks. Pre-compute the data in a `@php` block and pass a simple variable:

```php
@php $deviceJson = $devices->map(fn($d) => ['id' => $d->id, ...]); @endphp
// then:
const data = @json($deviceJson);
```

### Carbon 3 Gotcha

This project pins `nesbot/carbon: 3.13`, which made `diffInDays()` (and the other `diffInX()` methods) **signed by default** — a breaking change from Carbon 2's default-absolute behavior. `now()->diffInDays($pastDate)` returns a **negative** number, not positive. Always pass `absolute: true`/`false` explicitly rather than relying on the default: `absolute: true` for "days since a known past event" (always positive), `absolute: false` for "days until a possibly-future date" (positive when ahead, negative when past — `now()->diffInDays($futureDate)`). `SilicaStatus::evaluate()` is the reference implementation for both cases.
