# AI Smart Camera Dry Box — System Architecture

## 1. Purpose

The AI Smart Camera Dry Box is a monitored storage cabinet for camera equipment (lenses, bodies) that protects the equipment from humidity-related damage by continuously tracking internal temperature, humidity, and door activity, managing silica gel desiccant replacement against configurable thresholds, and alerting the user before conditions become damaging. Moisture control is passive (silica gel desiccant only — no electric dehumidifier), so the system's job is **detection and recommendation**, not active remediation.

The system follows the standard embedded systems pipeline:

**Sensor → Controller → Actuator → Cloud**

The alerting decision path is a **deterministic, rule-based engine** hosted in the Cloud tier (Laravel) — every automated alert (temperature, tamper, silica due/drift) traces back to a specific threshold crossing, not a model inference. An **on-demand Gemini AI suggestion** is available separately for silica gel replacement timing (analyzing the replacement log + recent sensor readings), but it does not drive any automated alert and only runs when the user explicitly asks for it.

---

## 2. Architecture: Sensor → Controller → Actuator → Cloud

```
┌───────────┐     ┌────────────┐     ┌────────────┐     ┌─────────────────┐
│  SENSOR   │ ──▶ │ CONTROLLER │ ──▶ │  ACTUATOR  │     │      CLOUD      │
│           │     │            │     │            │     │                 │
│ DHT22     │     │ ESP32      │     │ SSD1306    │     │ Firebase RTDB   │
│ (temp/    │     │ DevKit V1  │     │ OLED       │     │ (live sync +    │
│  humidity)│     │            │     │ display    │     │  protection_    │
│           │     │ Reads      │     │            │     │  mode flag)     │
│ FC-51 IR  │     │ sensors,   │     │ Passive    │     │                 │
│ door      │     │ classifies │     │ buzzer     │     │ Laravel +MySQL  │
│ sensor    │     │ status,    │     │ (alerting  │     │ (drybox:poll,   │
│ (digital  │     │ drives     │     │ only — no  │     │  AlertEvaluator,│
│ obstacle  │     │ actuators  │     │ moisture   │     │  SilicaStatus,  │
│ detect)   │     │ locally    │     │ actuator)  │     │  Gmail alerts)  │
└─────┬─────┘     └─────┬──────┘     └─────▲──────┘     └────────▲────────┘
      │                 │                  │                     │
      └─────────────────┴──────────────────┘                     │
                     controller drives                            │
                     actuator directly                            │
                                 │                                 │
                                 └─── pushes readings every 5s ────┘
                                      (Wi-Fi → Firebase RTDB)
```

Two things flow out of the Controller in parallel: an **immediate local signal to the Actuator** (fast, no history needed — OLED status text and buzzer tone) and a **continuous data stream to the Cloud** (Firebase RTDB, for the heavier, history-dependent rule engine that runs server-side in Laravel). This split keeps the embedded firmware lightweight while the real analytical work — rolling risk scoring, alert cooldowns, email dispatch — happens where there's compute and storage to do it properly.

---

## 3. Component Details

### 3.1 Sensor

- **DHT22** — temperature & humidity, GPIO 4. Physically limited to one fresh reading per ~2.1s; the firmware throttles reads to that interval and holds the last known-good value on a failed read (`drybox_esp32.ino`).
- **FC-51 IR obstacle-avoidance sensor** — door open/closed state, GPIO 5, single digital pin (no distance math, no debounce needed — detection range is set physically via the board's trimpot). `IR_TRIGGERED_STATE` (default `LOW`) defines which pin level means "door closed"; polled every loop for instant reaction to open/close transitions.
- Both are tagged to one device (`firebase_path`, e.g. `/drybox`) per physical unit in the `devices` table.

### 3.2 Controller

- **ESP32 DevKit V1** — reads both sensors continuously (non-blocking loop; buzzer state machine and door check never wait on Wi-Fi or DHT timing).
- Performs a **lightweight, immediate local classification**: `status = Critical` if humidity > `critHum`, `Warning` if > `warnHum`, else `Normal`. These thresholds start at compile-time fallback defaults and are **kept in sync with `device_settings.warn_humidity`/`crit_humidity`** via the same Firebase read-back described below — so a threshold change saved in the dashboard reaches the device without a reflash.
- Drives the **Actuator** (OLED + buzzer) directly and instantly on door state transitions — no round-trip to the cloud needed for local feedback.
- Streams raw readings to the **Cloud** (Firebase RTDB) every 5 seconds regardless of the local check's outcome; deliberately does **not** attempt rolling-window risk scoring itself — a microcontroller isn't the right place to hold days of history or run multi-factor scoring logic.
- Also **reads back** `protection_mode`, `warn_humidity`, `crit_humidity`, and `silica_days_left` from Firebase in a single periodic block (currently every 2s, tunable via `PROT_CHECK_INTERVAL` — intended to be raised to 15–30s in production to reduce reads), so a toggle or threshold change made in the web dashboard reaches local alarm/status behavior without a reflash. Each value is only overwritten on a successful read — offline or on read failure, the last known-good value (or compile-time default) is kept.

### 3.3 Actuator

- **SSD1306 OLED (I2C, SDA=21/SCL=22)** — shows live temperature, humidity, status (Normal / Warning / Critical), and a silica gel replacement countdown ("Replace in: N Days" / "Overdue by N Days").
- **Passive buzzer (GPIO 2)** — non-blocking state machine (`updateBuzzer()`):
  - `ALARM`/`ALARM_PAUSE` — continuous beeping while protection mode is ON and the door is open.
  - `OPEN_NOTIFY`/`OPEN_GAP` — two quick ascending beeps, once, when protection mode is OFF and the door opens.
  - `CALM` — three descending tones, once, on door close (any mode).
- **Design note:** the only moisture-control mechanism is passive silica gel — there is no electric dehumidifier for the Controller to drive. "Actuation" here means *alerting*, not *environmental correction*. This is a deliberate scope boundary: the system is monitor-and-alert, not monitor-and-auto-correct.

### 3.4 Cloud

This is where the real decision-making happens, split across Firebase (live state) and Laravel/MySQL (history, rules, notifications).

**Firebase Realtime Database** — live sync only, no history:
- Path `/{firebase_path}` holds `temperature`, `humidity`, `status`, `door`, `openCount`, `protection_mode`, `warn_humidity`, `crit_humidity`, `silica_days_left`.
- Browser reads this directly via the Firebase JS SDK for real-time UI updates.
- Laravel reads it server-side once a minute (`FirebaseReader`, kreait SDK) and writes back config values when the user changes them in the dashboard: `protection_mode` on toggle (`DeviceController::toggleProtection`), `warn_humidity`/`crit_humidity` on Settings save (`DryBoxController::saveSettings`), and `silica_days_left` every poll cycle (`PollDeviceData::handle`).

**MySQL storage (via Laravel)**

| Table | Purpose |
|---|---|
| `devices` | Registry of physical boxes — owner, name, location, `firebase_path` |
| `device_settings` | Per-device thresholds — `warn_humidity` (35), `crit_humidity` (45), `temp_min`/`temp_max`, `silica_last_replaced_at`, `silica_interval_days` (90), `silica_next_replacement_at` (nullable manual override), `protection_mode`, `notify_emails`, `alert_cooldown_minutes` (30) |
| `readings` | Per-minute sensor history — `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at` |
| `alerts` | Alert log — `device_id`, `type` (`temp`, `silica_due`, `silica_upcoming`, `silica_drift`, `tamper`; `humidity_warn`/`humidity_crit` retained for historical rows but no longer generated — live humidity is surfaced on the dashboard instead), `message`, `value`, `emailed_at`, `resolved_at` |
| `silica_replacements` | Replacement log — `device_id`, `replaced_at`, `interval_days_actual` |

**Rule-based alerting engine** — the classic four components of a rule-based system:

| Component | Implementation |
|---|---|
| Knowledge base | RH warn/crit bands, temperature min/max, silica replacement interval/thresholds |
| Facts | Sensor readings streamed up from the Controller via Firebase, polled into MySQL every minute |
| Inference engine | `AlertEvaluator` (temperature range + door tamper, cooldown-gated) and `SilicaStatus` (resolves a due date from either the fixed interval or a manual override, and derives due/warning state from it) |
| Output | Temperature/tamper alerts, silica gel replacement recommendations, and (on-demand only) a Gemini AI-assisted replacement suggestion |

**Why the alerting engine lives in the Cloud and not the Controller:**
- History-dependent logic (silica drift over days) needs data a microcontroller can't hold.
- Keeping the engine off the embedded device keeps firmware simple, testable, and cheap to run.
- The automated alerting path is rule-based and auditable — every alert traces back to a specific threshold crossing in `AlertEvaluator`/`SilicaStatus`. The Gemini AI suggestion (below) sits alongside this path, not inside it — it never fires an alert on its own.

**Silica gel replacement recommendation** — several independent signals, evaluated in the Cloud:
1. **Fixed interval** — `silica_last_replaced_at + silica_interval_days` (90 days by default), or a manual `silica_next_replacement_at` override when set → `silica_due`/`silica_upcoming` alerts (`PollDeviceData`, once per replacement cycle).
2. **Humidity drift** — closed-door humidity average over the first 3 days after replacement vs. the closed-door average over the last 24 hours; fires `silica_drift` (once/day) if it has risen ≥10 points, provided both windows have ≥10 closed-door samples. Independent of the fixed interval, so it catches gel saturating faster than the 90-day assumption.
3. **On-demand Gemini AI suggestion** — `GeminiSilicaAdvisor` analyzes the replacement log + recent sensor stats and suggests a next replacement date with reasoning, triggered manually from the Silica Log page. Not part of the automated alerting path.

**Notifications** — `SendGmailAlert` (per-alert) and `SendMonthlyReport` (1st of each month, CSV attachment via `ReportGenerator`) both send through `GmailApiSender`, using the user's own Gmail account via OAuth refresh token (no SMTP). An `invalid_grant` response from Gmail clears the stored refresh token rather than retrying indefinitely.

---

## 4. End-to-End Data Flow

1. **Sensor**: DHT22 and FC-51 are read continuously by the ESP32 (temperature/humidity throttled to ~2.1s, door state checked every loop).
2. **Controller**: classifies humidity status locally, drives the **Actuator** immediately on door transitions, and every 5s pushes the latest reading to **Firebase RTDB** regardless of the local classification.
3. **Browser**: reads Firebase directly for live dashboard updates (no round trip through Laravel).
4. **Cloud (Laravel, every 1 minute via `drybox:poll`)**:
   - Reads Firebase server-side (`FirebaseReader`, kreait SDK).
   - Stores a `Reading` row in MySQL.
   - Runs `AlertEvaluator::evaluate()` (temperature range + door tamper).
   - Checks silica gel due date (fires `silica_upcoming`/`silica_due` once per replacement cycle) and closed-door humidity drift (`silica_drift`, once/day).
   - Creates `Alert` rows and dispatches `SendGmailAlert` jobs for anything new (subject to per-type cooldown).
5. **Queue worker**: `SendGmailAlert` → `GmailApiSender` → Gmail API → user's inbox.
6. **Monthly**: `drybox:monthly-report` (1st of month, 01:00) dispatches `SendMonthlyReport` per recipient in `notify_emails`, attaching a CSV built by `ReportGenerator`.

---

## 5. Defensibility Summary

| Design decision | Rationale |
|---|---|
| Sensor → Controller → Actuator → Cloud pipeline | Standard embedded architecture; cleanly separates fast local response from heavier historical analysis |
| Rule-based alerting engine in Cloud (Laravel), not Controller | Multi-day drift detection needs history a microcontroller can't hold; keeps firmware lightweight |
| Rule-based automated alerting (not ML/LLM) | Deterministic thresholds; every alert is auditable back to a specific threshold crossing; the separate on-demand Gemini suggestion never fires an alert itself |
| Firebase for live sync, MySQL for history | Firebase RTDB is cheap, low-latency push/subscribe for "right now" values; MySQL is the durable, queryable historical record the alerting engine and reports run against |
| Actuator limited to alerting (no moisture actuator) | Silica gel is passive; system is explicitly monitor-and-alert, not monitor-and-correct |
| Multi-signal silica gel replacement logic | A fixed interval alone is fragile (gel can saturate early or last longer); pairing it with closed-door humidity drift, a manual override, and an on-demand AI suggestion covers more real-world cases |
| Alert cooldowns per device/type | Prevents email spam from a threshold being crossed repeatedly within a short window |
| Gmail via user's own OAuth account (no SMTP) | No shared mail server credentials to manage; `invalid_grant` handling keeps failures self-healing rather than retry-looping |

---

## 6. Out of Scope / Future Extensions

- **Active moisture actuator** (e.g. electric dehumidifier or heater) — would let the Actuator tier actually correct conditions rather than only alert. Not implemented; silica gel is the chosen moisture-control mechanism.
- **Camera/vision component** — no image sensor or computer-vision pipeline is currently implemented despite the "Smart Camera Dry Box" name; the system monitors the *storage environment* for camera equipment, not the equipment via imaging.
- **Agentic/autonomous AI** — the current Gemini integration is a single on-demand suggestion (user clicks a button, gets a date + reasoning), not a tool-calling or autonomous investigation layer. A broader agentic reporting layer on top of the alerting engine was considered but remains descoped: the deterministic engine already produces clear, explainable alerts without it.
