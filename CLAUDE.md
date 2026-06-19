# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**DryBox AI** — a Laravel 13 IoT monitoring dashboard for smart dry storage units. The system has a dual data flow: the browser reads Firebase directly for live sensor values, while a Laravel scheduler polls Firebase server-side every minute to persist history, evaluate alert rules, and send Gmail notifications.

## Development Commands

```bash
# Install dependencies
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
| `device_settings` | Per-device config — `warn_humidity` (35), `crit_humidity` (45), `silica_last_replaced_at`, `silica_interval_days` (90), `protection_mode`, `door_field`, `notify_emails` (JSON), `alert_cooldown_minutes` (30) |
| `readings` | Sensor history — `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at`; **no** `created_at`/`updated_at` (`$timestamps = false`) |
| `alerts` | Alert log — `type` enum (`humidity_warn`, `humidity_crit`, `temp`, `fungus`, `silica_due`, `tamper`), `value`, `emailed_at`, `resolved_at` |

### Models

All models use **PHP 8.3 attribute syntax**: `#[Fillable([...])]` and `#[Hidden([...])]` instead of `$fillable`/`$hidden` array properties.

`User` → `hasMany(Device)` → `hasOne(DeviceSetting)` + `hasMany(Reading)` + `hasMany(Alert)`

`User::google_refresh_token` uses `'encrypted'` cast. `Reading::$timestamps = false`.

### Controllers

- **`DryBoxController`** — page controllers; each method queries the user's primary active device (ordered by `created_at`) and passes `$device`, `$settings`, `$fungusRisk`, etc. to the view. `saveSettings()` handles `POST /settings`.
- **`AuthController`** — email/password auth + Google OAuth (`redirectToGoogle`, `handleGoogleCallback`). Callback finds-or-creates user by `google_id`, stores encrypted refresh token.
- **`DeviceController`** — `store`, `destroy`, `markSilicaReplaced`, `toggleProtection`. The last two return JSON for AJAX calls on the equipment page.
- **`ReportController`** — `generate()` streams a CSV download using `->cursor()` for readings (memory-safe) + pre-computed aggregate stats via `selectRaw`.
- **`DemoController`** — sets/clears a `demo_mode` session flag, returns demo Blade views.

### Services

- **`FirebaseReader`** (`app/Services/Firebase/`) — thin wrapper over kreait RTDB: `read(string $path): array`.
- **`AlertEvaluator`** — `evaluate(Device, Reading): array` returns triggered alert payloads; `evaluateFungus(Device, string $riskLevel): array`. Both check cooldown via `alerts` table to prevent duplicate emails within `alert_cooldown_minutes`.
- **`FungusRisk`** — `evaluate(Collection $readings, DeviceSetting): ['level' => Low|Moderate|High, 'score' => 0–100]`. Scores based on percentage of readings above warn/crit thresholds, weighted by whether temperature is in the 20–35 °C mould-growth band.
- **`GmailApiSender`** — sends email via Gmail API using the user's OAuth refresh token (no SMTP). Exchanges refresh token for access token, encodes MIME as `base64url`, POSTs to `gmail.googleapis.com`.

### Scheduler & Queue

`drybox:poll` runs every minute (registered in `routes/console.php`). For each active device it:
1. Reads Firebase via kreait
2. Stores a `Reading`
3. Runs `AlertEvaluator::evaluate()` (humidity/temp/tamper) + `AlertEvaluator::evaluateFungus()`
4. Checks silica gel due date (once per day, guarded by `whereDate('created_at', today())`)
5. Creates `Alert` rows and dispatches `SendGmailAlert` jobs

`SendGmailAlert` uses `QUEUE_CONNECTION=database`. On `invalid_grant` from Gmail it clears `google_refresh_token` rather than retrying.

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
