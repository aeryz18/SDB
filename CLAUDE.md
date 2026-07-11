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
```

The Firebase service-account JSON (`storage/app/firebase/credentials.json`) is required for the scheduler to read Firebase server-side. Without it, all browser-facing Firebase features still work, but `drybox:poll` will error. The file is gitignored via `storage/app/firebase/.gitignore`.

`GOOGLE_REDIRECT_URI` must **not** be set in `.env` — the redirect is derived from `APP_URL` in `config/services.php` to avoid Windows CRLF whitespace errors.

## Architecture

### Dual Data Flow

```
Browser (Firebase JS SDK v9 compat, CDN)
  └─ db.ref(device.firebase_path).on('value', ...) → live UI updates

Laravel Scheduler (drybox:poll, every minute)
  └─ FirebaseReader (kreait) → reads Firebase server-side
  └─ stores Reading row → evaluates AlertEvaluator + FungusRisk
  └─ creates Alert rows → dispatches SendGmailAlert job
  └─ queue worker → GmailApiSender (user's own Gmail via refresh token)
```

There is no `routes/api.php`. All device mutation routes (`/devices/*`, `/settings`, `/report`) are standard web routes returning redirects or file downloads.

### Database (MySQL `drybox_ai`)

Five application tables beyond the Laravel defaults:

| Table | Purpose |
|-------|---------|
| `devices` | Registry of physical boxes — `user_id`, `name`, `location`, `firebase_path` (unique), `is_active` |
| `device_settings` | Per-device config — `warn_humidity` (35), `crit_humidity` (45), `temp_min`/`temp_max` (nullable), `fungus_alerts_enabled` (true), `silica_last_replaced_at`, `silica_interval_days` (90), `protection_mode`, `door_field`, `notify_emails` (JSON), `alert_cooldown_minutes` (30) |
| `readings` | Sensor history — `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at`; **no** `created_at`/`updated_at` (`$timestamps = false`) |
| `alerts` | Alert log — `device_id`, `type` enum (`humidity_warn`, `humidity_crit`, `temp`, `fungus`, `silica_due`, `silica_drift`, `tamper`), `message`, `value`, `emailed_at`, `resolved_at`; composite index on `(device_id, type, resolved_at)`. `humidity_warn`/`humidity_crit` remain valid enum values for historical rows but are no longer generated — real-time humidity is surfaced on the live dashboard instead of by email. |

### Models

All models use **PHP 8.3 attribute syntax**: `#[Fillable([...])]` and `#[Hidden([...])]` instead of `$fillable`/`$hidden` array properties.

`User` → `hasMany(Device)` → `hasOne(DeviceSetting)` + `hasMany(Reading)` + `hasMany(Alert)`

`User::google_refresh_token` uses `'encrypted'` cast. `Reading::$timestamps = false`.

### Controllers

- **`DryBoxController`** — page controllers; each method queries the user's primary active device (ordered by `created_at`) and passes `$device`, `$settings`, `$fungusRisk`, etc. to the view. `saveSettings()` handles `POST /settings` — it only validates and saves `warn_humidity`, `crit_humidity`, `silica_interval_days`, and `notify_emails`; other settings (`temp_min`/`temp_max`, `protection_mode`, `fungus_alerts_enabled`) are mutated via AJAX through `DeviceController`.
- **`AuthController`** — email/password auth + Google OAuth (`redirectToGoogle`, `handleGoogleCallback`). Callback finds-or-creates user by `google_id`, stores encrypted refresh token.
- **`DeviceController`** — `store`, `destroy`, `markSilicaReplaced`, `toggleProtection`. The last two return JSON for AJAX calls on the equipment page.
- **`ReportController`** — `generate()` streams a CSV download built by `ReportGenerator` (which uses `->cursor()` for readings and pre-computed aggregate stats via `selectRaw`).
- **`DemoController`** — sets/clears a `demo_mode` session flag, returns demo Blade views.

### Services

- **`FirebaseReader`** (`app/Services/Firebase/`) — thin wrapper over kreait RTDB: `read(string $path): array`.
- **`AlertEvaluator`** — `evaluate(Device, Reading): array` checks temperature (`temp_min`/`temp_max`) and door tamper (no humidity threshold alerts — see below); `evaluateFungus(Device, string $riskLevel): array` fires only when `$riskLevel === FungusRisk::HIGH` and `fungus_alerts_enabled`. Both check cooldown via `alerts` table.
- **`FungusRisk`** — `evaluate(Collection $readings, DeviceSetting): ['level' => Low|Moderate|High, 'score' => 0–100]`. Scores based on percentage of readings above warn/crit thresholds, weighted by whether temperature is in the 20–35 °C mould-growth band.
- **`ReportGenerator`** — `generate(Device, Carbon $from, Carbon $to): string` builds the CSV condition report (readings, alerts, summary stats); `filename(...)` builds the matching download name. Shared by `ReportController::generate()` (on-demand download) and `SendMonthlyReport` (emailed attachment).
- **`GmailApiSender`** — sends email via Gmail API using the user's OAuth refresh token (no SMTP). Exchanges refresh token for access token, encodes MIME as `base64url`, POSTs to `gmail.googleapis.com`. `send()` sends a plain HTML message; `sendWithAttachment()` builds a `multipart/mixed` MIME message with a base64-encoded file part (used for the monthly report CSV).

### Scheduler & Queue

`drybox:poll` runs every minute (registered in `routes/console.php`). For each active device it:
1. Reads Firebase via kreait
2. Stores a `Reading`
3. Runs `AlertEvaluator::evaluate()` (temp/tamper — no humidity check) + `AlertEvaluator::evaluateFungus()`
4. Checks silica gel due date (once per day, guarded by `whereDate('created_at', today())`)
5. Checks silica gel humidity-drift (`PollDeviceData::checkSilicaDrift()`): compares the closed-door humidity average from the first `SILICA_BASELINE_WINDOW_DAYS` (3) after `silica_last_replaced_at` against the closed-door average over the last `SILICA_RECENT_WINDOW_HOURS` (24); fires a `silica_drift` alert (once/day) if it has risen by `SILICA_DRIFT_RH_THRESHOLD` (10 points) or more, provided both windows have at least `SILICA_MIN_SAMPLES` (10) closed-door readings. Independent of the fixed-interval check, so it can catch a gel saturating faster than `silica_interval_days` assumes.
6. Creates `Alert` rows and dispatches `SendGmailAlert` jobs

`SendGmailAlert` uses `QUEUE_CONNECTION=database`. On `invalid_grant` from Gmail it clears `google_refresh_token` rather than retrying.

`drybox:monthly-report` runs on the 1st of each month at 01:00 (registered in `routes/console.php`). For each active device it dispatches a `SendMonthlyReport` job per `notify_emails` recipient, covering the previous calendar month. The job builds the CSV via `ReportGenerator` and emails it as an attachment via `GmailApiSender::sendWithAttachment()` using `resources/views/emails/monthly-report.blade.php`. This runs alongside the on-demand `/report` download, not instead of it. Both `SendGmailAlert` and `SendMonthlyReport` clear `google_refresh_token` on `invalid_grant`.

### Google OAuth

Scopes requested: `openid email profile https://www.googleapis.com/auth/gmail.send` with `access_type=offline&prompt=consent`. The redirect URI **must be** `http://localhost:8000/auth/google/callback` — Google does not accept `.test` domains. Google Cloud Console requires the Gmail API enabled and the user added as an OAuth test user while the app is in "Testing" mode.

### Views & Layouts

Two layouts in `resources/views/layouts/`:
- `app.blade.php` — authenticated shell (topbar, sidebar, mobile bottom nav)
- `demo.blade.php` — identical plus "Demo Mode" banner and guided `DemoTour` JS class

Views use `@yield('content')` and `@yield('scripts')`. Firebase init and all live-data JS lives in `@section('scripts')` of each view. Demo views (`resources/views/demo/`) replace Firebase calls with `setInterval`-based simulation.

### Tailwind CSS

Loaded from CDN (`cdn.tailwindcss.com`) and configured via an inline `<script id="tailwind-config">` in both layout files — **not** via `tailwind.config.js` or Vite. The `@tailwindcss/vite` package is present but unused. Style changes must be applied to the inline config in **both** `layouts/app.blade.php` and `layouts/demo.blade.php`.

Design tokens: Material Design 3 color names as custom Tailwind classes, Space Grotesk for display/headlines, Inter for body. Icons: Google Material Symbols Outlined from CDN.

### Alert Thresholds

Thresholds (`warn_humidity`, `crit_humidity`) are stored in `device_settings` and passed server-side to every view. The settings page also writes them to `localStorage` (`warnThreshold`, `critThreshold`) as a backward-compat shim for the equipment page's live JS. Do not add new localStorage threshold reads — use the server-injected values.

### Blade + JS Gotcha

Never pass a multi-line `fn()` with nested parentheses directly to `@json()`. The Blade compiler counts `()` to find the directive boundary and breaks. Pre-compute the data in a `@php` block and pass a simple variable:

```php
@php $deviceJson = $devices->map(fn($d) => ['id' => $d->id, ...]); @endphp
// then:
const data = @json($deviceJson);
```
