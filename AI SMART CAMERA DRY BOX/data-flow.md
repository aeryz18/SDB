# Data Flow — Recording & Web Access

Companion to `system-architecture.md`, with more implementation detail: exactly how a
sensor reading ends up in MySQL, and the two separate paths the web pages use to read
data back out.

---

## 1. Recording — Firebase → MySQL

Data is never written to MySQL directly by the device. It flows through Firebase first.

### Step 1 — ESP32 writes to Firebase

`firmware/drybox_esp32/drybox_esp32.ino:413-429` — every 5 seconds the firmware pushes
live sensor values to the Firebase Realtime Database path `/drybox` (must match
`devices.firebase_path` in MySQL):

```cpp
Firebase.RTDB.setFloat(&fbdo,  FIREBASE_PATH + "/temperature", lastTemp);
Firebase.RTDB.setFloat(&fbdo,  FIREBASE_PATH + "/humidity",    lastHum);
Firebase.RTDB.setString(&fbdo, FIREBASE_PATH + "/status",      status);
Firebase.RTDB.setString(&fbdo, FIREBASE_PATH + "/door",        doorState);
Firebase.RTDB.setInt(&fbdo,    FIREBASE_PATH + "/openCount",   openCount);
```

At this point the data exists **only in Firebase** — it is not yet in MySQL.

### Step 2 — Laravel scheduler pulls Firebase into MySQL, every minute

`drybox:poll` (`app/Console/Commands/PollDeviceData.php`, registered in
`routes/console.php` via `Schedule::command('drybox:poll')->everyMinute()`) runs this
sequence for every **active** device:

1. **Read** — `FirebaseReader::read($device->firebase_path)` does a server-side kreait
   SDK read of whatever the current Firebase snapshot holds (`PollDeviceData.php:40`).
2. **Store** — `Reading::create([...])` inserts one row into the `readings` table:
   `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at => now()`
   (`PollDeviceData.php:50-57`).
3. **Evaluate rules** — runs `AlertEvaluator::evaluate()` + `evaluateFungus()` against
   this new reading plus the last 60 readings, and the silica-due / silica-drift checks
   (`PollDeviceData.php:68-95, 97-127`). Any triggered rule creates its own `Alert` row
   and dispatches a `SendGmailAlert` job.

**Consequence**: `readings` accumulates one row per device per minute — a *poll* of
whatever Firebase happened to hold at that moment, not a 1:1 mirror of every 5-second
ESP32 push. Anything the ESP32 pushed between two polls is simply overwritten in
Firebase and never captured in MySQL.

```
ESP32 ──(every 5s)──▶ Firebase RTDB ──(every 1min, drybox:poll)──▶ MySQL readings/alerts
```

---

## 2. Access — two separate paths

Web pages get data back out through two deliberately different routes, depending on
whether they need "right now" or "history."

### Path A — Live values: browser reads Firebase directly

Every dashboard/equipment page loads the Firebase JS SDK (v9 compat, CDN) in its
`@section('scripts')` block and does:

```js
db.ref(device.firebase_path).on('value', callback);
```

This **bypasses Laravel and MySQL entirely** — it's why the numbers on screen update
every ~5 seconds in real time, straight from Firebase to the browser.

### Path B — Historical data: browser → Laravel → MySQL → Blade

Anything that needs history (charts, trends, past alerts, CSV/email reports) goes
through `DryBoxController` (`app/Http/Controllers/DryBoxController.php`), which queries
MySQL and hands the result to a Blade view:

| Route | Controller method | What it queries |
|---|---|---|
| `GET /dashboard` | `dashboard()` | Last 24h of `readings` → runs `FungusRisk::evaluate()` live for the risk gauge (lines 24-31) |
| `GET /analytics` | `analytics()` | `readings` grouped by hour via `selectRaw`/`groupByRaw` for the chart, plus `alerts` counts, over a `from`/`to` range from the query string (lines 78-95) |
| `GET /report` | `ReportController::generate()` | Same idea via `ReportGenerator`, streamed out as a CSV download |
| `GET /equipment` | `equipment()` | Just the device/settings row — live values on this page still come from Path A's Firebase JS |

All four scope to `auth()->user()->devices()->where('is_active', true)->orderBy('created_at')->first()`
— the user's oldest active device. There's no device picker; it's a single-primary-device
assumption baked into the controller.

The monthly email report (`SendMonthlyReport` job) is a variant of Path B that runs
server-side on a schedule instead of per web request — it queries the same `readings`
table for a calendar-month range and emails a rendered summary instead of returning a
Blade view.

```
                    ┌── Path A: Firebase JS SDK ──▶ Browser (live "right now" values)
Browser request ────┤
                    └── Path B: DryBoxController ──▶ MySQL (readings/alerts) ──▶ Blade (history, charts, reports)
```

---

## Summary

| | Source | Freshness | Used for |
|---|---|---|---|
| **Path A** | Firebase RTDB, direct from browser | Live (~5s) | Current temp/humidity/door/status on dashboard & equipment pages |
| **Path B** | MySQL, via Laravel controllers | As of last `drybox:poll` run (≤1min old) | Charts, alert history, CSV reports, monthly emails, fungus risk scoring |

MySQL exists specifically because Path B's workload — `AVG`/`MAX`/`GROUP BY` hourly
aggregates, alert-cooldown lookups (`where('type', ...)->where('created_at', '>=', ...)`),
and Laravel's own queue (`jobs`/`failed_jobs`) — needs a relational/query engine that
Firebase RTDB doesn't provide. Firebase is kept for exactly what it's good at: pushing
live values to a browser with zero backend round-trip.
