"""
DryBox AI — Rule-Based Fungus Risk & Silica Gel Recommendation
==============================================================
Pure IF-THEN expert system: named rules fire against time-aggregated
statistics derived from sensor history stored in MySQL.

Usage:
    python fungus_risk_analysis.py --device-id 1 [--host localhost]
        [--port 3306] [--user root] [--password secret]
        [--database drybox_ai] [--output-csv results.csv]

    Missing --password prompts interactively.
"""

from __future__ import annotations

import argparse
import csv
import getpass
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

import pandas as pd
from sqlalchemy import create_engine, text

# ─────────────────────────────────────────────────────────────────────────────
# SECTION 1 — KNOWLEDGE BASE (all thresholds as named constants)
# ─────────────────────────────────────────────────────────────────────────────

# Fungus risk — per-rule thresholds
MOULD_TEMP_MIN = 20.0   # °C — optimal fungal growth band lower bound
MOULD_TEMP_MAX = 30.0   # °C — optimal fungal growth band upper bound

# Rule F1: critical spike — even a small fraction of readings above 80% is bad
PCT_ABOVE_80_HIGH = 10          # % time-weighted above 80% RH → HIGH

# Rule F2: sustained high humidity in mould-growth temperature
PCT_ABOVE_70_HIGH_RH   = 20     # % time above 70% RH  }
PCT_IN_MOULD_TEMP_HIGH = 50     # % readings in 20–30°C } both required → HIGH

# Rule F3: elevated humidity sustained over most of the window
PCT_ABOVE_60_MODERATE = 30      # % time above 60% RH → MODERATE

# Rule F4: frequent door-opens with elevated mean RH
DOOR_EVENTS_MODERATE   = 10     # door-open event count in 24h }
MEAN_RH_DOOR_MODERATE  = 55.0   # mean RH %                    } both → MODERATE

# Rule F5: any exposure above 70% combined with prime mould-growth temperature
PCT_ABOVE_70_PRIME_RH   = 5     # % time above 70% RH    }
PCT_IN_MOULD_TEMP_PRIME = 70    # % readings in 20–30°C  } both → MODERATE

LOOKBACK_HOURS = 72             # how far back to load readings

# Silica gel recommendation thresholds
GEL_BASELINE_WINDOW_HOURS = 24  # hours of closed-door readings to average for a baseline
GEL_ABSOLUTE_RH_THRESHOLD = 60  # Rule A — urgent: current baseline >= this value
GEL_DRIFT_THRESHOLD_PCT   = 8   # Rule B — saturation: baseline drifted this many pp
GEL_MAX_INTERVAL_DAYS     = 45  # Rule C — routine reminder interval

LEVEL_ORDER = {"LOW": 0, "MODERATE": 1, "HIGH": 2}

# ─────────────────────────────────────────────────────────────────────────────
# SECTION 2 — NAMED RULE DEFINITIONS
# ─────────────────────────────────────────────────────────────────────────────

def _fmt(template: str, stats: dict) -> str:
    return template.format(**{k: v if v is not None else "N/A" for k, v in stats.items()})


FUNGUS_RULES: list[dict] = [
    {
        "name": "critical_rh_spike",
        "level": "HIGH",
        "condition": lambda s: s["pct_above_80_rh"] >= PCT_ABOVE_80_HIGH,
        "reason": (
            "Above 80% RH for {pct_above_80_rh:.1f}% of the last 24 h "
            f"(threshold: {PCT_ABOVE_80_HIGH}%) — exceeds critical fungal-growth humidity."
        ),
    },
    {
        "name": "sustained_high_humidity_mould_temp",
        "level": "HIGH",
        "condition": lambda s: (
            s["pct_above_70_rh"] >= PCT_ABOVE_70_HIGH_RH
            and s["pct_in_mould_temp"] >= PCT_IN_MOULD_TEMP_HIGH
        ),
        "reason": (
            "Above 70% RH for {pct_above_70_rh:.1f}% of the last 24 h "
            f"(threshold: {PCT_ABOVE_70_HIGH_RH}%) "
            "while {pct_in_mould_temp:.1f}% of readings fell in the optimal "
            f"mould-growth temperature band ({MOULD_TEMP_MIN}–{MOULD_TEMP_MAX} °C)."
        ),
    },
    {
        "name": "elevated_humidity_sustained",
        "level": "MODERATE",
        "condition": lambda s: s["pct_above_60_rh"] >= PCT_ABOVE_60_MODERATE,
        "reason": (
            "Above 60% RH for {pct_above_60_rh:.1f}% of the last 24 h "
            f"(threshold: {PCT_ABOVE_60_MODERATE}%) — "
            "fungal growth may begin at sustained levels above 60% RH."
        ),
    },
    {
        "name": "frequent_door_elevated_rh",
        "level": "MODERATE",
        "condition": lambda s: (
            s["door_open_events"] >= DOOR_EVENTS_MODERATE
            and s["mean_rh"] >= MEAN_RH_DOOR_MODERATE
        ),
        "reason": (
            "{door_open_events} door-open events in the last 24 h "
            f"(threshold: {DOOR_EVENTS_MODERATE}) "
            "with a mean RH of {mean_rh:.1f}% "
            f"(threshold: {MEAN_RH_DOOR_MODERATE}%) — "
            "frequent exposure to ambient air at elevated humidity."
        ),
    },
    {
        "name": "moderate_humidity_prime_temp",
        "level": "MODERATE",
        "condition": lambda s: (
            s["pct_above_70_rh"] >= PCT_ABOVE_70_PRIME_RH
            and s["pct_in_mould_temp"] >= PCT_IN_MOULD_TEMP_PRIME
        ),
        "reason": (
            "Above 70% RH for {pct_above_70_rh:.1f}% of the last 24 h "
            f"(threshold: {PCT_ABOVE_70_PRIME_RH}%) "
            "while {pct_in_mould_temp:.1f}% of readings were in the prime "
            f"mould-growth temperature band ({MOULD_TEMP_MIN}–{MOULD_TEMP_MAX} °C, "
            f"threshold: {PCT_IN_MOULD_TEMP_PRIME}%)."
        ),
    },
]

# ─────────────────────────────────────────────────────────────────────────────
# SECTION 3 — DB CONNECTION & TABLE BOOTSTRAP
# ─────────────────────────────────────────────────────────────────────────────

GEL_LOG_DDL = """
CREATE TABLE IF NOT EXISTS gel_replacement_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id   BIGINT UNSIGNED NOT NULL,
    replaced_at DATETIME NOT NULL,
    note        VARCHAR(255) NULL,
    CONSTRAINT fk_gel_device
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
)
"""


def build_engine(host: str, port: int, user: str, password: str, database: str):
    pwd_part = f":{password}" if password and password.strip() else ""
    url = f"mysql+mysqlconnector://{user}{pwd_part}@{host}:{port}/{database}?charset=utf8mb4"
    return create_engine(url, pool_pre_ping=True)


def ensure_gel_log_table(engine) -> None:
    with engine.begin() as conn:
        conn.execute(text(GEL_LOG_DDL))


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 4 — DATA LOADING
# ─────────────────────────────────────────────────────────────────────────────

def load_readings(engine, device_id: int, hours: int = LOOKBACK_HOURS) -> pd.DataFrame:
    cutoff = datetime.now(timezone.utc) - timedelta(hours=hours)
    sql = text("""
        SELECT recorded_at, temperature, humidity, door_state
        FROM   readings
        WHERE  device_id = :did
          AND  recorded_at >= :cutoff
        ORDER  BY recorded_at
    """)
    with engine.connect() as conn:
        df = pd.read_sql(sql, conn, params={"did": device_id, "cutoff": cutoff})

    if df.empty:
        return df

    df["recorded_at"] = pd.to_datetime(df["recorded_at"], utc=True)
    df["door_state"]  = df["door_state"].fillna("").str.lower().str.strip()
    df = df.set_index("recorded_at").sort_index()
    return df


def load_device_settings(engine, device_id: int) -> dict:
    sql = text("""
        SELECT warn_humidity, crit_humidity, silica_interval_days, silica_last_replaced_at
        FROM   device_settings
        WHERE  device_id = :did
        LIMIT  1
    """)
    with engine.connect() as conn:
        row = conn.execute(sql, {"did": device_id}).mappings().fetchone()

    if row is None:
        return {
            "warn_humidity": 35,
            "crit_humidity": 45,
            "silica_interval_days": GEL_MAX_INTERVAL_DAYS,
            "silica_last_replaced_at": None,
        }
    return dict(row)


def load_gel_log(engine, device_id: int, settings: dict) -> dict | None:
    """
    Return the most recent gel replacement event, preferring gel_replacement_log.
    Falls back to device_settings.silica_last_replaced_at when the log is empty.
    """
    sql = text("""
        SELECT replaced_at, note
        FROM   gel_replacement_log
        WHERE  device_id = :did
        ORDER  BY replaced_at DESC
        LIMIT  1
    """)
    with engine.connect() as conn:
        row = conn.execute(sql, {"did": device_id}).mappings().fetchone()

    if row:
        return {"replaced_at": row["replaced_at"], "source": "gel_replacement_log"}

    fallback = settings.get("silica_last_replaced_at")
    if fallback:
        return {"replaced_at": fallback, "source": "device_settings (fallback)"}

    return None


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 5 — FUNGUS RISK ENGINE
# ─────────────────────────────────────────────────────────────────────────────

def detect_door_events(df: pd.DataFrame) -> pd.Series:
    """
    Mark each row where door_state transitions to 'open' from a non-open state.
    A physical open lasting several seconds appears as consecutive 'open' rows;
    this detects the state TRANSITION, not raw 'open' row counts.
    """
    is_open  = df["door_state"] == "open"
    was_open = is_open.shift(1, fill_value=False)
    return (is_open & ~was_open).astype(int)


def compute_stats(df: pd.DataFrame, window_hours: int = 24) -> dict:
    """
    Compute time-weighted aggregate statistics over the trailing `window_hours`.

    Time-weighting: each reading r_i is assigned the duration to the next reading
    r_{i+1} (forward gap).  The last reading gets zero weight.  This makes
    percentages independent of sampling rate and robust to data gaps.
    """
    if df.empty:
        return {
            "pct_above_80_rh": 0.0,
            "pct_above_70_rh": 0.0,
            "pct_above_60_rh": 0.0,
            "mean_rh": 0.0,
            "pct_in_mould_temp": 0.0,
            "door_open_events": 0,
        }

    cutoff = df.index[-1] - pd.Timedelta(hours=window_hours)
    window = df[df.index >= cutoff].copy()

    if window.empty:
        return compute_stats(pd.DataFrame(), window_hours)

    # Door events (row-count based — transitions are point-in-time, not durations)
    window["_door_event"] = detect_door_events(window)
    door_open_events = int(window["_door_event"].sum())

    # Time-weighted percentages
    ts = window.index
    gaps_ns = ts[1:].values.astype("int64") - ts[:-1].values.astype("int64")
    gaps_s  = gaps_ns / 1e9  # convert nanoseconds → seconds

    # Each reading's weight = the gap to the next reading (last row gets 0)
    weights = pd.Series(
        list(gaps_s) + [0.0],
        index=window.index,
        name="_weight",
    )
    total_s = weights.sum()

    def pct_above(col: pd.Series, threshold: float) -> float:
        if total_s <= 0:
            return 0.0
        return float((weights[col >= threshold].sum() / total_s) * 100)

    def pct_condition(mask: pd.Series) -> float:
        if total_s <= 0:
            return 0.0
        return float((weights[mask].sum() / total_s) * 100)

    in_band = (window["temperature"] >= MOULD_TEMP_MIN) & (window["temperature"] <= MOULD_TEMP_MAX)

    mean_rh = float(window["humidity"].mean()) if not window["humidity"].isna().all() else 0.0

    return {
        "pct_above_80_rh":   pct_above(window["humidity"], 80),
        "pct_above_70_rh":   pct_above(window["humidity"], 70),
        "pct_above_60_rh":   pct_above(window["humidity"], 60),
        "mean_rh":           mean_rh,
        "pct_in_mould_temp": pct_condition(in_band),
        "door_open_events":  door_open_events,
    }


def evaluate_fungus_risk(df: pd.DataFrame) -> dict:
    """
    Inference engine: check each named rule against 24h aggregates.
    Returns level (LOW/MODERATE/HIGH), alert flag, fired rule names, and reasons.
    """
    stats = compute_stats(df, window_hours=24)
    fired = [r for r in FUNGUS_RULES if r["condition"](stats)]

    if fired:
        level = max((r["level"] for r in fired), key=lambda l: LEVEL_ORDER[l])
    else:
        level = "LOW"

    alert   = level == "HIGH"
    reasons = [_fmt(r["reason"], stats) for r in fired]

    return {
        "level":       level,
        "alert":       alert,
        "fired_rules": [r["name"] for r in fired],
        "reasons":     reasons,
        "stats":       stats,
    }


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 6 — SILICA GEL RECOMMENDATION ENGINE
# ─────────────────────────────────────────────────────────────────────────────

def _to_utc_ts(dt) -> pd.Timestamp:
    """Convert any datetime-like to a UTC-aware pd.Timestamp safely."""
    ts = pd.Timestamp(dt)
    if ts.tzinfo is None:
        ts = ts.tz_localize("UTC")
    else:
        ts = ts.tz_convert("UTC")
    return ts


def _closed_door_mean(df: pd.DataFrame, start: pd.Timestamp, hours: int) -> float | None:
    """Mean RH for door-closed readings in [start, start + hours)."""
    end  = start + pd.Timedelta(hours=hours)
    mask = (
        (df.index >= start)
        & (df.index <= end)
        & (df["door_state"] != "open")
    )
    subset = df.loc[mask, "humidity"].dropna()
    return float(subset.mean()) if len(subset) >= 10 else None


def compute_current_baseline(df: pd.DataFrame) -> float | None:
    if df.empty:
        return None
    end   = df.index[-1]
    start = end - pd.Timedelta(hours=GEL_BASELINE_WINDOW_HOURS)
    return _closed_door_mean(df, start, GEL_BASELINE_WINDOW_HOURS)


def compute_start_baseline(df: pd.DataFrame, replaced_at) -> float | None:
    if replaced_at is None or df.empty:
        return None
    return _closed_door_mean(df, _to_utc_ts(replaced_at), GEL_BASELINE_WINDOW_HOURS)


def evaluate_gel(df: pd.DataFrame, gel_log: dict | None, settings: dict) -> dict:
    current_baseline  = compute_current_baseline(df)
    replaced_at       = gel_log["replaced_at"] if gel_log else None
    start_baseline    = compute_start_baseline(df, replaced_at)

    drift             = None
    days_since        = None

    if replaced_at is not None:
        replaced_utc = _to_utc_ts(replaced_at).to_pydatetime()
        days_since = (datetime.now(timezone.utc) - replaced_utc).days

    if current_baseline is not None and start_baseline is not None:
        drift = round(current_baseline - start_baseline, 2)

    recommend = False
    reasons: list[str] = []

    # Rule A — urgent: absolute high baseline
    if current_baseline is not None and current_baseline >= GEL_ABSOLUTE_RH_THRESHOLD:
        recommend = True
        reasons.append(
            f"[Rule A] Closed-door RH baseline is {current_baseline:.1f}% — "
            f"at or above the urgent threshold ({GEL_ABSOLUTE_RH_THRESHOLD}%)."
        )

    # Rule B — saturation: baseline drifted up since last replacement
    if drift is not None and drift >= GEL_DRIFT_THRESHOLD_PCT:
        recommend = True
        reasons.append(
            f"[Rule B] Closed-door RH has risen {drift:+.1f} pp since the last replacement "
            f"(from {start_baseline:.1f}% to {current_baseline:.1f}%) — "
            "gel is losing moisture-absorbing capacity."
        )

    # Rule C — routine time-based reminder
    interval = settings.get("silica_interval_days") or GEL_MAX_INTERVAL_DAYS
    if days_since is not None and days_since >= interval:
        recommend = True
        reasons.append(
            f"[Rule C] {days_since} days since last logged replacement "
            f"(routine interval: {interval} days)."
        )
    elif days_since is None:
        reasons.append(
            "[Rule C] No replacement has ever been logged for this device — "
            "consider logging the current gel's start date in gel_replacement_log."
        )

    return {
        "recommend":          recommend,
        "reasons":            reasons,
        "current_baseline_rh": current_baseline,
        "start_baseline_rh":  start_baseline,
        "drift":              drift,
        "days_since_replacement": days_since,
        "gel_source":         gel_log["source"] if gel_log else None,
    }


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 7 — OUTPUT
# ─────────────────────────────────────────────────────────────────────────────

def print_report(device_id: int, fungus: dict, gel: dict) -> None:
    s = fungus["stats"]
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M")

    print(f"\n{'='*65}")
    print(f"  DryBox Fungus Risk Report — Device #{device_id}  ({now_str})")
    print(f"{'='*65}")
    print(f"  Risk level : {fungus['level']}")
    print(f"  Alert      : {'YES ⚠' if fungus['alert'] else 'NO'}")

    if fungus["fired_rules"]:
        print("\n  Rules fired:")
        for rule_name, reason in zip(fungus["fired_rules"], fungus["reasons"]):
            level_tag = next(
                r["level"] for r in FUNGUS_RULES if r["name"] == rule_name
            )
            print(f"    [{level_tag:<8}] {rule_name}")
            for line in reason.split(". "):
                line = line.strip()
                if line:
                    print(f"               {line}.")
    else:
        print("\n  No risk rules fired — conditions are within safe limits.")

    print(f"\n  Supporting statistics (last 24 h):")
    print(f"    Mean RH                  : {s['mean_rh']:.1f}%")
    print(f"    % time above 60% RH      : {s['pct_above_60_rh']:.1f}%")
    print(f"    % time above 70% RH      : {s['pct_above_70_rh']:.1f}%")
    print(f"    % time above 80% RH      : {s['pct_above_80_rh']:.1f}%")
    print(f"    % in mould-temp band     : {s['pct_in_mould_temp']:.1f}%")
    print(f"    Door-open events         : {s['door_open_events']}")

    print(f"\n{'='*65}")
    print(f"  Silica Gel Recommendation")
    print(f"{'='*65}")

    def _fmt_rh(v):
        return f"{v:.1f}%" if v is not None else "N/A (insufficient closed-door readings)"

    print(f"  Current closed-door RH baseline : {_fmt_rh(gel['current_baseline_rh'])}")
    print(f"  Baseline at last replacement    : {_fmt_rh(gel['start_baseline_rh'])}")
    drift_str = (
        f"{gel['drift']:+.1f} pp" if gel["drift"] is not None else "N/A"
    )
    print(f"  Drift since replacement         : {drift_str}")
    days_str = (
        str(gel["days_since_replacement"]) if gel["days_since_replacement"] is not None else "never logged"
    )
    print(f"  Days since replacement          : {days_str}")
    if gel["gel_source"]:
        print(f"  Replacement data source         : {gel['gel_source']}")
    print(f"  Recommend replacement           : {'YES' if gel['recommend'] else 'NO'}")
    if gel["reasons"]:
        print("\n  Reasons:")
        for r in gel["reasons"]:
            print(f"    {r}")
    print()


def write_csv(path: str, device_id: int, fungus: dict, gel: dict) -> None:
    s        = fungus["stats"]
    file_    = Path(path)
    is_new   = not file_.exists()
    now_str  = datetime.now().isoformat(timespec="seconds")

    row = {
        "recorded_at":           now_str,
        "device_id":             device_id,
        "fungus_level":          fungus["level"],
        "fungus_alert":          int(fungus["alert"]),
        "fired_rules":           "|".join(fungus["fired_rules"]),
        "mean_rh_24h":           round(s["mean_rh"], 2),
        "pct_above_60_rh_24h":   round(s["pct_above_60_rh"], 2),
        "pct_above_70_rh_24h":   round(s["pct_above_70_rh"], 2),
        "pct_above_80_rh_24h":   round(s["pct_above_80_rh"], 2),
        "pct_in_mould_temp_24h": round(s["pct_in_mould_temp"], 2),
        "door_open_events_24h":  s["door_open_events"],
        "gel_recommend":         int(gel["recommend"]),
        "gel_current_baseline":  gel["current_baseline_rh"],
        "gel_start_baseline":    gel["start_baseline_rh"],
        "gel_drift_pp":          gel["drift"],
        "gel_days_since":        gel["days_since_replacement"],
    }

    with file_.open("a", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=list(row.keys()))
        if is_new:
            writer.writeheader()
        writer.writerow(row)

    print(f"  Results appended to {path}")


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 8 — SELF-TEST (no DB required)
# ─────────────────────────────────────────────────────────────────────────────

def _make_df(rows: list[dict]) -> pd.DataFrame:
    """Build a properly indexed DataFrame from a list of dicts."""
    df = pd.DataFrame(rows)
    df["recorded_at"] = pd.to_datetime(df["recorded_at"], utc=True)
    df["door_state"]  = df["door_state"].str.lower()
    return df.set_index("recorded_at").sort_index()


def _assert(label: str, got, expected) -> bool:
    ok = got == expected
    status = "PASS" if ok else "FAIL"
    print(f"  [{status}] {label}")
    if not ok:
        print(f"         expected={expected!r}  got={got!r}")
    return ok


def run_self_tests() -> None:
    now = datetime.now(timezone.utc)

    def ts(hours_ago: float, minutes_offset: float = 0) -> str:
        return (now - timedelta(hours=hours_ago, minutes=minutes_offset)).isoformat()

    failures = 0

    # ── Test 1: door-event detection ─────────────────────────────────────────
    print("\n[Test 1] Door-event detection")
    door_df = _make_df([
        {"recorded_at": ts(2, 5), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(2, 4), "temperature": 25, "humidity": 50, "door_state": "open"},   # event
        {"recorded_at": ts(2, 3), "temperature": 25, "humidity": 50, "door_state": "open"},
        {"recorded_at": ts(2, 2), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(2, 1), "temperature": 25, "humidity": 50, "door_state": "open"},   # event
        {"recorded_at": ts(2, 0), "temperature": 25, "humidity": 50, "door_state": "open"},
    ])
    events = int(detect_door_events(door_df).sum())
    if not _assert("detects 2 events from 4 'open' rows", events, 2):
        failures += 1

    # ── Test 2: Rule F1 fires at HIGH (>= 10% time above 80% RH) ────────────
    print("\n[Test 2] Rule F1 — critical_rh_spike → HIGH")
    # 15 minutes above 80% RH in a 90-minute window ≈ 16.7%
    rows_f1 = [
        {"recorded_at": ts(1, 90), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(1, 75), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(1, 15), "temperature": 25, "humidity": 85, "door_state": "closed"},  # 15 min above 80
        {"recorded_at": ts(1,  0), "temperature": 25, "humidity": 85, "door_state": "closed"},
    ]
    result = evaluate_fungus_risk(_make_df(rows_f1))
    if not _assert("level is HIGH", result["level"], "HIGH"):
        failures += 1
    if not _assert("critical_rh_spike fired", "critical_rh_spike" in result["fired_rules"], True):
        failures += 1

    # ── Test 3: Rule F2 fires at HIGH (sustained 70%+ with mould temp) ───────
    print("\n[Test 3] Rule F2 — sustained_high_humidity_mould_temp → HIGH")
    # 6 h above 70% RH in 24 h = 25%; temperature 25°C (in band)
    rows_f2 = [
        {"recorded_at": ts(24), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(18), "temperature": 25, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(6),  "temperature": 25, "humidity": 75, "door_state": "closed"},  # 6 h above 70
        {"recorded_at": ts(0),  "temperature": 25, "humidity": 75, "door_state": "closed"},
    ]
    result = evaluate_fungus_risk(_make_df(rows_f2))
    if not _assert("level is HIGH", result["level"], "HIGH"):
        failures += 1
    if not _assert("sustained_high_humidity_mould_temp fired", "sustained_high_humidity_mould_temp" in result["fired_rules"], True):
        failures += 1

    # ── Test 4: Rule F3 fires at MODERATE (>= 30% time above 60% RH) ────────
    print("\n[Test 4] Rule F3 — elevated_humidity_sustained → MODERATE")
    # 8 h above 60% RH in 24 h ≈ 33%
    rows_f3 = [
        {"recorded_at": ts(24), "temperature": 15, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(16), "temperature": 15, "humidity": 50, "door_state": "closed"},
        {"recorded_at": ts(8),  "temperature": 15, "humidity": 65, "door_state": "closed"},  # 8 h above 60
        {"recorded_at": ts(0),  "temperature": 15, "humidity": 65, "door_state": "closed"},
    ]
    result = evaluate_fungus_risk(_make_df(rows_f3))
    if not _assert("level is MODERATE", result["level"], "MODERATE"):
        failures += 1
    if not _assert("elevated_humidity_sustained fired", "elevated_humidity_sustained" in result["fired_rules"], True):
        failures += 1

    # ── Test 5: Rule F4 fires at MODERATE (frequent door + high mean RH) ─────
    print("\n[Test 5] Rule F4 — frequent_door_elevated_rh → MODERATE")
    # 11 door-open events with mean RH 60%
    door_rows = []
    base_time = now - timedelta(hours=20)
    door_rows.append({"recorded_at": (base_time - timedelta(hours=1)).isoformat(), "temperature": 15, "humidity": 60, "door_state": "closed"})
    for i in range(11):
        open_t  = base_time + timedelta(minutes=i * 60)
        close_t = open_t + timedelta(minutes=5)
        door_rows.append({"recorded_at": open_t.isoformat(),  "temperature": 15, "humidity": 60, "door_state": "open"})
        door_rows.append({"recorded_at": close_t.isoformat(), "temperature": 15, "humidity": 60, "door_state": "closed"})
    result = evaluate_fungus_risk(_make_df(door_rows))
    if not _assert("frequent_door_elevated_rh fired", "frequent_door_elevated_rh" in result["fired_rules"], True):
        failures += 1

    # ── Test 6: safe conditions → LOW ────────────────────────────────────────
    print("\n[Test 6] Safe conditions → LOW, no alert")
    rows_safe = [
        {"recorded_at": ts(24), "temperature": 25, "humidity": 35, "door_state": "closed"},
        {"recorded_at": ts(12), "temperature": 25, "humidity": 38, "door_state": "closed"},
        {"recorded_at": ts(0),  "temperature": 25, "humidity": 36, "door_state": "closed"},
    ]
    result = evaluate_fungus_risk(_make_df(rows_safe))
    if not _assert("level is LOW", result["level"], "LOW"):
        failures += 1
    if not _assert("no alert", result["alert"], False):
        failures += 1
    if not _assert("no rules fired", result["fired_rules"], []):
        failures += 1

    # ── Test 7: gel Rule A fires (absolute high baseline) ────────────────────
    print("\n[Test 7] Gel Rule A — absolute high baseline")
    # All closed-door readings in last 24h at 65% RH
    gel_rows = [
        {"recorded_at": ts(23), "temperature": 25, "humidity": 65, "door_state": "closed"},
        {"recorded_at": ts(12), "temperature": 25, "humidity": 65, "door_state": "closed"},
        {"recorded_at": ts(1),  "temperature": 25, "humidity": 65, "door_state": "closed"},
        {"recorded_at": ts(0),  "temperature": 25, "humidity": 65, "door_state": "closed"},
    ] * 3  # repeat to hit the >=10 minimum row count
    gel_df = _make_df(gel_rows)
    gel_result = evaluate_gel(gel_df, None, {"silica_interval_days": 45, "silica_last_replaced_at": None})
    if not _assert("recommend=True", gel_result["recommend"], True):
        failures += 1
    if not _assert("Rule A in reasons", any("Rule A" in r for r in gel_result["reasons"]), True):
        failures += 1

    # ── Test 8: gel Rule B fires (drift >= 8 pp) ─────────────────────────────
    print("\n[Test 8] Gel Rule B — saturation drift")
    replaced = now - timedelta(days=20)
    # First 24h after replacement: 30% RH (start baseline)
    start_rows = [{"recorded_at": (replaced + timedelta(hours=h)).isoformat(), "temperature": 25, "humidity": 30, "door_state": "closed"} for h in range(0, 24, 2)]
    # Last 24h: 40% RH (current baseline)
    end_rows   = [{"recorded_at": (now - timedelta(hours=h)).isoformat(), "temperature": 25, "humidity": 40, "door_state": "closed"} for h in range(0, 24, 2)]
    gel_df2 = _make_df(start_rows + end_rows)
    gel_log2 = {"replaced_at": replaced, "source": "test"}
    gel_result2 = evaluate_gel(gel_df2, gel_log2, {"silica_interval_days": 45, "silica_last_replaced_at": None})
    if not _assert("drift detected (>= 8 pp)", gel_result2["drift"] is not None and gel_result2["drift"] >= 8, True):
        failures += 1
    if not _assert("Rule B in reasons", any("Rule B" in r for r in gel_result2["reasons"]), True):
        failures += 1

    # ── Test 9: gel Rule C fires (overdue) ───────────────────────────────────
    print("\n[Test 9] Gel Rule C — overdue replacement")
    old_replaced = now - timedelta(days=50)
    gel_log3 = {"replaced_at": old_replaced, "source": "test"}
    gel_result3 = evaluate_gel(_make_df(gel_rows), gel_log3, {"silica_interval_days": 45, "silica_last_replaced_at": None})
    if not _assert("days_since >= 50", gel_result3["days_since_replacement"] >= 50, True):
        failures += 1
    if not _assert("Rule C in reasons", any("Rule C" in r for r in gel_result3["reasons"]), True):
        failures += 1

    # ── Summary ───────────────────────────────────────────────────────────────
    total = 15  # number of _assert calls above
    passed = total - failures
    print(f"\n{'='*45}")
    print(f"  Self-test result: {passed}/{total} checks passed")
    if failures:
        print(f"  {failures} check(s) FAILED — review output above.")
        sys.exit(1)
    else:
        print("  All checks passed.")
    print()


# ─────────────────────────────────────────────────────────────────────────────
# SECTION 9 — CLI & MAIN
# ─────────────────────────────────────────────────────────────────────────────

def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="DryBox rule-based fungus risk & silica gel recommendation"
    )
    p.add_argument("--self-test", action="store_true", dest="self_test",
                   help="Run built-in rule validation tests (no DB needed) and exit")
    p.add_argument("--host",       default="localhost")
    p.add_argument("--port",       type=int, default=3306)
    p.add_argument("--user",       default="root")
    p.add_argument("--password",   default=None,
                   help="MySQL password (prompted if omitted)")
    p.add_argument("--database",   default="drybox_ai")
    p.add_argument("--device-id",  type=int, dest="device_id",
                   help="Required unless --self-test is used")
    p.add_argument("--output-csv", default=None, dest="output_csv",
                   metavar="PATH",
                   help="Append one summary row to this CSV file")
    return p.parse_args()


def main() -> None:
    args = parse_args()

    if args.self_test:
        run_self_tests()
        return

    if args.device_id is None:
        print("error: --device-id is required (or use --self-test)", file=sys.stderr)
        sys.exit(1)

    password = args.password
    if password is None and sys.stdin.isatty():
        password = getpass.getpass(
            f"MySQL password for {args.user}@{args.host}/{args.database} (Enter for none): "
        )

    print(f"Connecting to {args.user}@{args.host}:{args.port}/{args.database} …")
    engine = build_engine(args.host, args.port, args.user, password, args.database)

    ensure_gel_log_table(engine)

    print(f"Loading readings for device #{args.device_id} (last {LOOKBACK_HOURS} h) …")
    df = load_readings(engine, args.device_id)

    if df.empty:
        print(
            f"\nNo readings found for device #{args.device_id} in the last "
            f"{LOOKBACK_HOURS} hours. Nothing to analyse.",
            file=sys.stderr,
        )
        sys.exit(1)

    settings = load_device_settings(engine, args.device_id)
    gel_log  = load_gel_log(engine, args.device_id, settings)

    fungus = evaluate_fungus_risk(df)
    gel    = evaluate_gel(df, gel_log, settings)

    print_report(args.device_id, fungus, gel)

    if args.output_csv:
        write_csv(args.output_csv, args.device_id, fungus, gel)


if __name__ == "__main__":
    main()
