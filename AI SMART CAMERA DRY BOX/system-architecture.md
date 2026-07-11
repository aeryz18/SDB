# AI Smart Camera Dry Box — System Architecture

## 1. Purpose

The AI Smart Camera Dry Box is a monitored storage cabinet for camera equipment (lenses, bodies) that prevents fungal growth on optics by continuously tracking internal temperature, humidity, and door activity, scoring fungal risk against literature-grounded thresholds, and alerting the user before conditions become damaging. Moisture control is passive (silica gel desiccant only — no electric dehumidifier), so the system's job is **detection and recommendation**, not active remediation.

The system follows the standard embedded systems pipeline:

**Sensor → Controller → Actuator → Cloud**

The decision-making core is a **deterministic, rule-based risk engine** hosted in the Cloud tier (Laravel) — there is no AI/LLM component anywhere in the decision path. ("AI" in the product name refers to the intelligent alerting/risk-scoring behavior, not a machine-learning model.)

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
│ obstacle  │     │ actuators  │     │ moisture   │     │  FungusRisk,    │
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
- Performs a **lightweight, immediate local classification**: `status = crit` if humidity > 45%, `warn` if > 35%, else `normal` (`WARN_HUM`/`CRIT_HUM` constants, mirrored from `device_settings.warn_humidity`/`crit_humidity` on the Laravel side).
- Drives the **Actuator** (OLED + buzzer) directly and instantly on door state transitions — no round-trip to the cloud needed for local feedback.
- Streams raw readings to the **Cloud** (Firebase RTDB) every 5 seconds regardless of the local check's outcome; deliberately does **not** attempt rolling-window risk scoring itself — a microcontroller isn't the right place to hold days of history or run multi-factor scoring logic.
- Also **reads back** the `protection_mode` flag from Firebase (currently every 2s, tunable via `PROT_CHECK_INTERVAL` — intended to be raised to 15–30s in production to reduce reads) so a toggle made in the web dashboard changes local alarm behavior.

### 3.3 Actuator

- **SSD1306 OLED (I2C, SDA=21/SCL=22)** — shows live temperature, humidity, door state + open count, and status (OK / WARN / blinking "HIGH HUMIDITY" on crit).
- **Passive buzzer (GPIO 2)** — non-blocking state machine (`updateBuzzer()`):
  - `ALARM`/`ALARM_PAUSE` — continuous beeping while protection mode is ON and the door is open.
  - `OPEN_NOTIFY`/`OPEN_GAP` — two quick ascending beeps, once, when protection mode is OFF and the door opens.
  - `CALM` — three descending tones, once, on door close (any mode).
- **Design note:** the only moisture-control mechanism is passive silica gel — there is no electric dehumidifier for the Controller to drive. "Actuation" here means *alerting*, not *environmental correction*. This is a deliberate scope boundary: the system is monitor-and-alert, not monitor-and-auto-correct.

### 3.4 Cloud

This is where the real decision-making happens, split across Firebase (live state) and Laravel/MySQL (history, rules, notifications).

**Firebase Realtime Database** — live sync only, no history:
- Path `/{firebase_path}` holds `temperature`, `humidity`, `status`, `door`, `openCount`, `protection_mode`.
- Browser reads this directly via the Firebase JS SDK for real-time UI updates.
- Laravel reads it server-side once a minute (`FirebaseReader`, kreait SDK) and writes `protection_mode` when the user toggles it in the dashboard (`DeviceController::toggleProtection`).

**MySQL storage (via Laravel)**

| Table | Purpose |
|---|---|
| `devices` | Registry of physical boxes — owner, name, location, `firebase_path` |
| `device_settings` | Per-device thresholds — `warn_humidity` (35), `crit_humidity` (45), `temp_min`/`temp_max`, `fungus_alerts_enabled`, `silica_last_replaced_at`, `silica_interval_days` (90), `protection_mode`, `notify_emails`, `alert_cooldown_minutes` (30) |
| `readings` | Per-minute sensor history — `device_id`, `temperature`, `humidity`, `status`, `door_state`, `recorded_at` |
| `alerts` | Alert log — `device_id`, `type` (`temp`, `fungus`, `silica_due`, `silica_drift`, `tamper`; `humidity_warn`/`humidity_crit` retained for historical rows but no longer generated — live humidity is surfaced on the dashboard instead), `message`, `value`, `emailed_at`, `resolved_at` |

**Rule-based risk engine** — the classic four components of a rule-based recommendation system:

| Component | Implementation |
|---|---|
| Knowledge base | Fungal biology thresholds — RH warn/crit bands, mould-growth temperature band (20–35 °C) |
| Facts | Sensor readings streamed up from the Controller via Firebase, polled into MySQL every minute |
| Inference engine | `AlertEvaluator` (temperature range + door tamper, cooldown-gated) and `FungusRisk` (scores 0–100 from % of readings above warn/crit thresholds, weighted by whether temperature sits in the mould-growth band) |
| Output | `Low`/`Moderate`/`High` fungus risk level, temperature/tamper alerts, and silica gel replacement recommendations |

**Why the risk engine lives in the Cloud and not the Controller:**
- History-dependent scoring (fungus risk over recent readings, silica drift over days) needs data a microcontroller can't hold.
- Keeping the engine off the embedded device keeps firmware simple, testable, and cheap to run.
- A rule-based (not AI/LLM) engine is transparent and auditable — every alert traces back to a specific threshold crossing in `AlertEvaluator`/`FungusRisk`.

**Silica gel replacement recommendation** — two independent signals, both evaluated in the Cloud (`PollDeviceData`):
1. **Fixed interval** — `silica_last_replaced_at + silica_interval_days` (90 days), checked once/day → `silica_due` alert.
2. **Humidity drift** — closed-door humidity average over the first 3 days after replacement vs. the closed-door average over the last 24 hours; fires `silica_drift` (once/day) if it has risen ≥10 points, provided both windows have ≥10 closed-door samples. Independent of the fixed interval, so it catches gel saturating faster than the 90-day assumption.

**Notifications** — `SendGmailAlert` (per-alert) and `SendMonthlyReport` (1st of each month, CSV attachment via `ReportGenerator`) both send through `GmailApiSender`, using the user's own Gmail account via OAuth refresh token (no SMTP). An `invalid_grant` response from Gmail clears the stored refresh token rather than retrying indefinitely.

---

## 4. End-to-End Data Flow

1. **Sensor**: DHT22 and FC-51 are read continuously by the ESP32 (temperature/humidity throttled to ~2.1s, door state checked every loop).
2. **Controller**: classifies humidity status locally, drives the **Actuator** immediately on door transitions, and every 5s pushes the latest reading to **Firebase RTDB** regardless of the local classification.
3. **Browser**: reads Firebase directly for live dashboard updates (no round trip through Laravel).
4. **Cloud (Laravel, every 1 minute via `drybox:poll`)**:
   - Reads Firebase server-side (`FirebaseReader`, kreait SDK).
   - Stores a `Reading` row in MySQL.
   - Runs `AlertEvaluator::evaluate()` (temperature range + door tamper) and `AlertEvaluator::evaluateFungus()` (only when `FungusRisk` reports `High` and fungus alerts are enabled).
   - Once/day: checks silica gel due date and closed-door humidity drift.
   - Creates `Alert` rows and dispatches `SendGmailAlert` jobs for anything new (subject to per-type cooldown).
5. **Queue worker**: `SendGmailAlert` → `GmailApiSender` → Gmail API → user's inbox.
6. **Monthly**: `drybox:monthly-report` (1st of month, 01:00) dispatches `SendMonthlyReport` per recipient in `notify_emails`, attaching a CSV built by `ReportGenerator`.

---

## 5. Defensibility Summary

| Design decision | Rationale |
|---|---|
| Sensor → Controller → Actuator → Cloud pipeline | Standard embedded architecture; cleanly separates fast local response from heavier historical analysis |
| Rule-based risk engine in Cloud (Laravel), not Controller | Rolling-window scoring and multi-day drift detection need history a microcontroller can't hold; keeps firmware lightweight |
| Rule-based engine (not ML/LLM) | Deterministic biology thresholds; no labeled training data; every alert is auditable back to a specific threshold crossing |
| Firebase for live sync, MySQL for history | Firebase RTDB is cheap, low-latency push/subscribe for "right now" values; MySQL is the durable, queryable historical record the risk engine and reports run against |
| Actuator limited to alerting (no moisture actuator) | Silica gel is passive; system is explicitly monitor-and-alert, not monitor-and-correct |
| Two-signal silica gel replacement logic | A fixed interval alone is fragile (gel can saturate early or last longer); pairing it with closed-door humidity drift catches both cases |
| Alert cooldowns per device/type | Prevents email spam from a threshold being crossed repeatedly within a short window |
| Gmail via user's own OAuth account (no SMTP) | No shared mail server credentials to manage; `invalid_grant` handling keeps failures self-healing rather than retry-looping |

---

## 6. Out of Scope / Future Extensions

- **Active moisture actuator** (e.g. electric dehumidifier or heater) — would let the Actuator tier actually correct conditions rather than only alert. Not implemented; silica gel is the chosen moisture-control mechanism.
- **Camera/vision component** — no image sensor or computer-vision pipeline is currently implemented despite the "Smart Camera Dry Box" name; the system monitors the *storage environment* for camera equipment, not the equipment via imaging.
- **Agentic AI / LLM reporting layer** — a tool-calling layer for natural-language investigation on top of the risk engine was considered but descoped: the rule engine already produces clear, explainable output without the added compute/API cost and complexity.
