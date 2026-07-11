# Silica Gel Replacement System

This document explains how DryBox AI tracks silica gel desiccant replacement — the fixed-interval and adaptive drift checks, the manual date override, the single per-device notify threshold, the Replace Silica Gel flow, the replacement log, and the on-demand Gemini AI suggestion.

## 1. Overview

Silica gel absorbs moisture inside the dry box but saturates over time and stops working. The system tracks replacement several ways:

- A fixed-interval estimate (`silica_last_replaced_at` + `silica_interval_days`).
- An optional manual override (`silica_next_replacement_at`) that takes precedence over the estimate when set.
- An adaptive humidity-drift check that can catch a gel saturating faster than the fixed interval assumes.
- A full replacement history log, viewable on its own page (`/silica-log`).
- An on-demand Gemini AI suggestion for the next replacement date, based on the log and recent sensor readings.

All of the due-date/warning/overdue math is centralized in `App\Services\SilicaStatus::evaluate()` — the single source of truth used by the dashboard, the Equipment page, the Silica Log page, and the scheduler.

## 2. Due-date resolution

`SilicaStatus::evaluate(?DeviceSetting $settings)` resolves one due date:

1. If `silica_next_replacement_at` is set, it wins (`source = 'manual'`).
2. Otherwise, if `silica_last_replaced_at` is set, the due date is `silica_last_replaced_at + silica_interval_days` (`source = 'interval'`).
3. Otherwise there's no due date at all (`source = 'unset'`) — treated as due immediately.

`days_since` and the progress bar (`bar_pct`) are always derived from `silica_last_replaced_at` and the interval, regardless of which source the due date came from — the progress bar always shows "how long since the gel was actually replaced," even while a manual override is active.

**Carbon 3 gotcha**: `diffInDays()` is signed by default in this project's Carbon version (a change from Carbon 2's default-absolute behavior). `days_since` uses `absolute: true` (always positive, "days since a known past event"); `days_left` uses `absolute: false` (`now()->diffInDays($dueDate)`, positive when the due date is ahead, negative when overdue — "days remaining"). Both are explicit rather than relying on the default, so a future Carbon default change can't silently break this again.

## 3. Manual override and the notify threshold

`silica_next_replacement_at` (date, nullable) is the manual due-date override, and `silica_notify_days_before` (int, default 7) is the single per-device lead time that drives both the in-app warning state (status badge color, dashboard login banner) and the once-per-cycle `silica_upcoming` email — there is no separate hardcoded UI-vs-email threshold; both read the same value off `$silica['notify_days_before']`. Both fields are set together via the Replace Silica Gel modal (§4) or independently via the standalone "Adjust Next Replacement Plan" form (`POST /devices/{device}/silica-next-replacement`), which also has a "Revert to automatic" action that clears the date. Setting or clearing the override resolves any outstanding `silica_due`/`silica_upcoming` alerts, so the alerting guard re-arms against the new due date instead of staying silent because an alert from the old cycle is still open.

The overdue reminder has no separate threshold — it's simply `SilicaStatus::evaluate()`'s `due` flag (`days_left <= 0`), fired once per cycle.

**Email alerts fire once per replacement cycle, not daily.** `PollDeviceData::fireSilicaAlert()` guards on "an unresolved alert of this type already exists" (`whereNull('resolved_at')`) rather than "already alerted today." `markSilicaReplaced()` and `updateSilicaNextReplacementAt()` both resolve outstanding `silica_due`/`silica_upcoming` alerts, which re-arms the guard for the next cycle. In practice this means at most two silica emails per cycle: one when crossing into the notify window, one when first detected overdue.

**In-app banner**: rendered server-side in `dashboard.blade.php` from `$silica['warning'] || $silica['due']` — no session flag, no JS. It re-evaluates on every dashboard load, so it keeps appearing on every login until the gel is marked replaced (or the due date otherwise moves out of the warning/overdue range).

## 4. Replace Silica Gel flow

Clicking "Replace Silica Gel" on the Silica Log page opens a modal rather than acting immediately. The modal:

1. Pre-fills a next-replacement date (today + `silica_interval_days`) and the current `silica_notify_days_before`.
2. Has an "Ask AI for a suggestion" button — calls `GeminiSilicaAdvisor` and shows a suggested date + reasoning with a "Use this date" button that fills the date field with one click. The user can also ignore the suggestion entirely and pick their own date; nothing is applied automatically.
3. Requires **confirmation** (`confirm()`, summarizing the chosen date and notify lead time) before the request is sent.
4. Submits a single `POST /devices/{device}/silica` with both `next_replacement_at` and `notify_days_before` — `markSilicaReplaced()` validates both as required, logs the `SilicaReplacement` row, and sets `silica_last_replaced_at`/`silica_next_replacement_at`/`silica_notify_days_before` together.
5. On success, shows a toast with an 8-second Undo window (deletes the row, restores the previous `silica_last_replaced_at`, and reverts `silica_next_replacement_at` to null) before reloading.

The Equipment page's per-device card only links to `/silica-log` for this action — it no longer has its own inline "Mark Replaced" flow, since the new form requires more input than a card button can reasonably hold.

## 5. Adaptive drift check (`silica_drift`)

Unchanged from before: `PollDeviceData::checkSilicaDrift()` compares closed-door humidity in the first `SILICA_BASELINE_WINDOW_DAYS` (3 days) after `silica_last_replaced_at` against the closed-door average over the last `SILICA_RECENT_WINDOW_HOURS` (24 hours); fires `silica_drift` (still daily-guarded, not part of the once-per-cycle change above) if it has risen `SILICA_DRIFT_RH_THRESHOLD` (10 points) or more, provided both windows have at least `SILICA_MIN_SAMPLES` (10) closed-door readings. Only runs when `silica_last_replaced_at` is set (it needs a real replacement baseline — a manual-only override with no logged replacement has nothing to compare against).

## 6. Replacement log

Every replacement (via the modal in §4) logs a `SilicaReplacement` row (`replaced_at`, `interval_days_actual` — the gap since the previous replacement, null for the first-ever one). The Silica Log page shows the full paginated history, newest first, plus an average-lifespan stat once at least two completed intervals exist.

## 7. Gemini AI suggestion

`App\Services\GeminiSilicaAdvisor::suggest(Device $device)` builds a compact prompt from the device's replacement history (dates + `interval_days_actual`) and aggregated sensor stats (avg/peak humidity and temperature since the last replacement), and asks the Gemini API for a suggested next replacement date with brief reasoning. On-demand only — triggered by "Ask AI for a suggestion" inside the Replace Silica Gel modal (`POST /devices/{device}/silica-ai-suggestion`), no scheduled job, no persistence. The suggestion only fills the date field on explicit "Use this date" click — it's never applied automatically. Requires `GEMINI_API_KEY` (from Google AI Studio, aistudio.google.com) and `GEMINI_MODEL` in `.env`. A failed/timed-out Gemini call returns a friendly error in the modal rather than crashing the page.

## 8. Dedicated Silica Log page

`GET /silica-log` (`DryBoxController::silicaLog()`) is a full page (nav entry: "Silica Log") containing the status card, the Replace Silica Gel modal, the standalone adjustment form, and the full history list. The Equipment page keeps only a compact per-device silica indicator plus a link to this page — the full detail view lives here, not duplicated across pages.
