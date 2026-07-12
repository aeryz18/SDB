/**
 * DryBox AI — ESP32 Firmware v3
 *
 * Hardware:
 *   ESP32 DevKit V1, DHT22 (GPIO4),
 *   FC-51 IR obstacle-avoidance door sensor (single digital OUT pin, GPIO5),
 *   SSD1306 OLED I2C (SDA=21, SCL=22), Passive buzzer (GPIO2)
 *
 * FC-51 wiring note: power it from the ESP32's 3.3V pin (not 5V/VIN). Run at
 * 3.3V its OUT pin is already a safe logic level for the GPIO directly — no
 * voltage divider needed (unlike the old HC-SR04). Adjust the onboard trimpot
 * so OUT triggers right around your door's closed distance (~2.5cm), then
 * confirm which logic level means "detected" using the [Sensor] serial print
 * below and flip IR_TRIGGERED_STATE if it's backwards.
 *
 * Libraries (Arduino Library Manager):
 *   Firebase ESP Client by Mobizt, DHT sensor library by Adafruit,
 *   Adafruit GFX Library, Adafruit SSD1306
 */

#include <WiFi.h>
#include <Firebase_ESP_Client.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include "DHT.h"
#include "secrets.h"   // WIFI_SSID, WIFI_PASSWORD, API_KEY, DATABASE_URL — gitignored

// ─── CONFIG ───────────────────────────────────────────────────────────────────
#define FIREBASE_PATH  "/drybox"   // must match firebase_path in Laravel DB

// ─── PINS ────────────────────────────────────────────────────────────────────
#define DHTPIN        4
#define DHTTYPE       DHT22
#define IR_PIN        5     // FC-51 digital OUT pin
#define BUZZER_PIN    2

// ─── DOOR SENSOR (FC-51 IR obstacle avoidance) ────────────────────────────────
// Single digital pin — no distance math, no debounce needed. Detection range is
// set physically via the trimpot on the FC-51 board, not in code.
// CALIBRATE THIS: most FC-51 boards pull OUT LOW when an obstacle is detected
// (door closed) and HIGH when clear (door open) — that's what LOW=triggered
// assumes below. Watch the "[Sensor] raw=..." serial print with the door
// physically open vs closed; if it's backwards, flip IR_TRIGGERED_STATE to HIGH.
#define IR_TRIGGERED_STATE  LOW   // pin level that means "obstacle detected" (door closed)

// ─── OLED ────────────────────────────────────────────────────────────────────
#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT 64
#define OLED_ADDR     0x3C

// ─── HUMIDITY THRESHOLDS (match Laravel device_settings; overwritten by ─────
// Firebase read-back below once connected — see "READ CONFIG from Firebase") ──
float warnHum = 60.0;   // offline/fallback default until first successful Firebase read
float critHum = 70.0;

// ─── OBJECTS ─────────────────────────────────────────────────────────────────
FirebaseData     fbdo;
FirebaseAuth     auth;
FirebaseConfig   config;
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);
DHT              dht(DHTPIN, DHTTYPE);

// ─── DOOR STATE (FC-51 digital) ────────────────────────────────────────────────
// No debounce — digitalRead() is instant and clean (unlike the old ultrasonic
// distance readings), so the door flips the moment the pin state changes.
bool          lastDoorReading = false;   // true = open, false = closed
bool          stableDoor      = false;
bool          doorOpenNow     = false;   // latest state, used by updateBuzzer()
int    openCount = 0;
String doorState = "closed";
bool   prevDoorOpen = false;

// ─── PROTECTION MODE (read from Firebase) ────────────────────────────────────
bool          protectionMode    = false;
unsigned long lastProtCheckMs   = 0;
#define PROT_CHECK_INTERVAL     2000    // re-read from Firebase every 2s (was 30s — shortened for testing; raise back to 15000-30000 for production to reduce Firebase reads)

// ─── SILICA GEL REPLACEMENT COUNTDOWN (read from Firebase) ──────────────────
int silicaDaysLeft = 9999;   // sentinel: "not yet synced from Firebase" — real values are roughly -3650..3650

// ─── BUZZER STATE MACHINE ────────────────────────────────────────────────────
// ALARM/ALARM_PAUSE : continuous beeping (protection mode ON, door open)
// OPEN_NOTIFY       : two gentle beeps once (protection mode OFF, door open)
// OPEN_GAP          : silence between the two open-notify beeps
// CALM              : three descending tones once (door closed, any mode)

enum BuzzerMode { SILENT, ALARM, ALARM_PAUSE, OPEN_NOTIFY, OPEN_GAP, CALM };
BuzzerMode    buzzerMode = SILENT;
unsigned long buzzerMs   = 0;
int           seqStep    = 0;   // shared step counter for OPEN_NOTIFY and CALM

// Alarm (continuous, protection ON)
#define ALARM_BEEP_MS   300
#define ALARM_PAUSE_MS  150
#define ALARM_FREQ      2000   // reverted — 3200 was inaudible/weak on this buzzer

// Open notify (two quick ascending beeps, played once, protection OFF)
const int OPEN_FREQS[]     = { 900, 1200 };
const int OPEN_DURATIONS[] = { 100, 100 };
#define   OPEN_GAP_MS       80
#define   OPEN_STEPS        2

// Calm (three descending tones, door closed)
const int CALM_FREQS[]     = { 900, 650, 420 };
const int CALM_DURATIONS[] = { 150, 150, 250 };
#define   CALM_GAP_MS       60
#define   CALM_STEPS        3

// ─── TIMERS ──────────────────────────────────────────────────────────────────
unsigned long lastSendMs   = 0;
unsigned long lastBlinkMs  = 0;
unsigned long lastCalibMs  = 0;   // for the sensor calibration printout
unsigned long lastDhtMs    = 0;
#define DHT_READ_INTERVAL_MS 2100   // DHT22 needs ~2s between reads to return fresh data
bool          blinkState   = true;
float         lastTemp     = 0.0;   // updated once the first DHT read succeeds
float         lastHum      = 0.0;

// ─── BUZZER UPDATE (non-blocking, call every loop) ───────────────────────────
void updateBuzzer() {
  unsigned long now = millis();

  switch (buzzerMode) {

    // ── Continuous alarm (protection mode ON, door open) ─────────────────────
    case ALARM:
      if (now - buzzerMs >= ALARM_BEEP_MS) {
        noTone(BUZZER_PIN);
        buzzerMode = ALARM_PAUSE;
        buzzerMs   = now;
      }
      break;

    case ALARM_PAUSE:
      if (now - buzzerMs >= ALARM_PAUSE_MS) {
        if (doorOpenNow) {   // still open → keep alarming
          tone(BUZZER_PIN, ALARM_FREQ);
          buzzerMode = ALARM;
          buzzerMs   = now;
        } else {
          noTone(BUZZER_PIN);
          buzzerMode = SILENT;
        }
      }
      break;

    // ── Single open notify (protection mode OFF, door open) ──────────────────
    case OPEN_NOTIFY:
      if (now - buzzerMs >= (unsigned long)OPEN_DURATIONS[seqStep]) {
        noTone(BUZZER_PIN);
        seqStep++;
        if (seqStep < OPEN_STEPS) {
          buzzerMode = OPEN_GAP;   // short gap before next beep
          buzzerMs   = now;
        } else {
          buzzerMode = SILENT;     // done — no continuous alarm
          seqStep    = 0;
        }
      }
      break;

    case OPEN_GAP:
      if (now - buzzerMs >= OPEN_GAP_MS) {
        tone(BUZZER_PIN, OPEN_FREQS[seqStep]);
        buzzerMode = OPEN_NOTIFY;
        buzzerMs   = now;
      }
      break;

    // ── Calm close sound (any mode, door closed) ──────────────────────────────
    case CALM:
      if (now - buzzerMs >= (unsigned long)(CALM_DURATIONS[seqStep] + CALM_GAP_MS)) {
        noTone(BUZZER_PIN);
        seqStep++;
        if (seqStep < CALM_STEPS) {
          tone(BUZZER_PIN, CALM_FREQS[seqStep]);
          buzzerMs = now;
        } else {
          buzzerMode = SILENT;
          seqStep    = 0;
        }
      }
      break;

    case SILENT:
    default:
      break;
  }
}

// ─── SETUP ───────────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);

  Wire.begin(21, 22);
  pinMode(IR_PIN, INPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  // Read real door state at boot — fixes initialization mismatch
  int bootRaw     = digitalRead(IR_PIN);
  stableDoor      = (bootRaw != IR_TRIGGERED_STATE);   // true = open
  lastDoorReading = stableDoor;
  doorOpenNow     = stableDoor;
  doorState       = stableDoor ? "open" : "closed";
  Serial.printf("Boot door state: %s (raw pin=%s, triggered state=%s)\n",
                doorState.c_str(), bootRaw == HIGH ? "HIGH" : "LOW",
                IR_TRIGGERED_STATE == HIGH ? "HIGH" : "LOW");

  // OLED
  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR)) {
    Serial.println("OLED failed");
    while (true);
  }
  display.setRotation(2);   // flip 180 degrees (0=normal, 1=90, 2=180, 3=270)
  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);
  display.setTextSize(1);
  display.setCursor(20, 24);
  display.println("DryBox AI");
  display.setCursor(10, 38);
  display.println("Starting...");
  display.display();

  // DHT
  dht.begin();

  // WiFi (with timeout so the board never hangs silently on "Starting...")
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);

  // Diagnostic scan — tells us if the SSID is even visible to the ESP32
  Serial.println("Scanning for WiFi networks...");
  int netCount = WiFi.scanNetworks();
  bool ssidSeen = false;
  if (netCount == 0) {
    Serial.println("  No networks found at all — check the ESP32 antenna/power.");
  } else {
    for (int i = 0; i < netCount; i++) {
      String foundSsid = WiFi.SSID(i);
      Serial.printf("  [%d] %s  RSSI:%d  enc:%d\n",
                    i, foundSsid.c_str(), WiFi.RSSI(i), WiFi.encryptionType(i));
      if (foundSsid == String(WIFI_SSID)) ssidSeen = true;
    }
    Serial.println(ssidSeen
      ? "  -> Target SSID WAS found in scan."
      : "  -> Target SSID was NOT found in scan (wrong name, hidden, out of range, or 5GHz-only).");
  }

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting WiFi");
  unsigned long wifiStartMs = millis();
  const unsigned long WIFI_TIMEOUT_MS = 15000;
  while (WiFi.status() != WL_CONNECTED && millis() - wifiStartMs < WIFI_TIMEOUT_MS) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.printf("\nWiFi FAILED to connect (timeout). status code=%d "
                  "(1=NO_SSID_AVAIL, 4=CONNECT_FAILED/bad password, 6=DISCONNECTED)\n",
                  WiFi.status());
    Serial.println("Check SSID/password — ESP32 only supports 2.4GHz networks. "
                    "Retrying in background...");
    display.clearDisplay();
    display.setCursor(10, 20);
    display.println("WiFi FAILED");
    display.setCursor(0, 34);
    display.println("Check SSID/pass");
    display.setCursor(0, 46);
    display.println("(2.4GHz only)");
    display.display();
    delay(3000);   // let the message be readable, then continue into loop()
  } else {
    Serial.println("\nWiFi connected: " + WiFi.localIP().toString());
  }

  // Firebase
  config.api_key      = API_KEY;
  config.database_url = DATABASE_URL;
  Firebase.signUp(&config, &auth, "", "");
  Firebase.begin(&config, &auth);
  Firebase.reconnectWiFi(true);

  Serial.println("System ready");
  // Startup beep — deliberately NOT using tone(pin, freq, duration). That 3-arg
  // form sets up an internal auto-timed one-shot on the ESP32's LEDC hardware
  // that doesn't mix well with the manual tone()/noTone() pairs used everywhere
  // else in updateBuzzer(), and can leave the buzzer stuck on long after it
  // should have stopped. Manual start/delay/stop keeps it consistent.
  tone(BUZZER_PIN, 1000);
  delay(200);
  noTone(BUZZER_PIN);
}

// ─── LOOP ────────────────────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  updateBuzzer();

  // ── DOOR (FC-51 digital) ───────────────────────────────────────────────────
  // digitalRead() is instant and clean — check it every loop, no throttling,
  // no debounce. React the moment the pin state changes.
  int  rawPin = digitalRead(IR_PIN);
  bool reading = (rawPin != IR_TRIGGERED_STATE);   // true = open

  if (reading != stableDoor) {
    stableDoor = reading;
    doorOpenNow = stableDoor;
    bool doorOpen = stableDoor;
    doorState = doorOpen ? "open" : "closed";

    if (doorOpen) {
      openCount++;
      Serial.printf("Door OPENED — total opens: %d  protectionMode: %s\n",
                    openCount, protectionMode ? "ON" : "OFF");
      noTone(BUZZER_PIN);
      seqStep = 0;
      if (protectionMode) {
        // Continuous alarm
        tone(BUZZER_PIN, ALARM_FREQ);
        buzzerMode = ALARM;
      } else {
        // Two quick beeps, then silent
        tone(BUZZER_PIN, OPEN_FREQS[0]);
        buzzerMode = OPEN_NOTIFY;
      }
      buzzerMs = now;
    } else {
      Serial.println("Door CLOSED");
      noTone(BUZZER_PIN);
      seqStep = 0;
      buzzerMode = SILENT;   // no chime on close — just stop
    }
  }
  lastDoorReading = reading;

  // ── SENSOR PRINT — watch this while testing to confirm IR_TRIGGERED_STATE
  //    is set correctly for your FC-51 board (should match physical door state)
  if (now - lastCalibMs > 1000) {
    lastCalibMs = now;
    Serial.printf("[Sensor] raw=%s  door: %s\n",
                  rawPin == HIGH ? "HIGH" : "LOW", doorState.c_str());
  }

  // ── DHT22 — read only every DHT_READ_INTERVAL_MS, never blocks door/buzzer ──
  // DHT22 physically can't produce fresh data faster than ~2s. Reading it every
  // loop (like before) meant frequent NaN results, and an early `return` on NaN
  // used to skip the door check AND buzzer update entirely — that was the real
  // cause of the buzzer/door "waiting" on DHT timing instead of reacting live.
  if (now - lastDhtMs >= DHT_READ_INTERVAL_MS) {
    lastDhtMs = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t) && !isnan(h)) {
      lastTemp = t;
      lastHum  = h;
    }   // on failure, just keep the last known-good reading
  }

  // ── STATUS (matches Laravel thresholds) ───────────────────────────────────
  String status;
  if (lastHum > critHum)      status = "Critical";
  else if (lastHum > warnHum) status = "Warning";
  else                          status = "Normal";

  // ── BLINK for OLED warning ────────────────────────────────────────────────
  if (now - lastBlinkMs > 500) {
    blinkState = !blinkState;
    lastBlinkMs = now;
  }

  // ── OLED DISPLAY ──────────────────────────────────────────────────────────
  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);

  display.setTextSize(1);
  display.setCursor(20, 0);
  display.println("Smart Dry Box");
  display.drawLine(0, 10, 128, 10, SSD1306_WHITE);

  display.setCursor(0, 16);
  display.print("Temperature: ");
  display.print(lastTemp, 1);
  display.print("C");

  display.setCursor(0, 28);
  display.print("Humidity: ");
  display.print(lastHum, 1);
  display.print("%");

  display.setCursor(0, 40);
  display.print("Status: ");
  display.print(status);

  display.setCursor(0, 52);
  if (silicaDaysLeft >= 9999) {
    display.print("Replace: --");
  } else if (silicaDaysLeft <= 0) {
    display.print("Overdue by ");
    display.print(-silicaDaysLeft);
    display.print(" Days");
  } else {
    display.print("Replace in: ");
    display.print(silicaDaysLeft);
    display.print(" Days");
  }

  display.display();

  // ── FIREBASE PUSH every 5 seconds ─────────────────────────────────────────
  // Each RTDB call below blocks on network I/O (can be 100-500ms+ over WiFi).
  // updateBuzzer() is called between each one so a mid-sequence tone (e.g. the
  // CALM chime) keeps advancing in real time instead of freezing on one note
  // until the whole Firebase block finishes.
  if (now - lastSendMs > 5000 && Firebase.ready()) {
    lastSendMs = now;

    Firebase.RTDB.setFloat(&fbdo,  String(FIREBASE_PATH) + "/temperature", lastTemp);
    updateBuzzer();
    Firebase.RTDB.setFloat(&fbdo,  String(FIREBASE_PATH) + "/humidity",    lastHum);
    updateBuzzer();
    Firebase.RTDB.setString(&fbdo, String(FIREBASE_PATH) + "/status",      status);
    updateBuzzer();
    Firebase.RTDB.setString(&fbdo, String(FIREBASE_PATH) + "/door",        doorState);
    updateBuzzer();
    Firebase.RTDB.setInt(&fbdo,    String(FIREBASE_PATH) + "/openCount",   openCount);
    updateBuzzer();

    Serial.printf("[Firebase] temp=%.1f hum=%.1f status=%s door=%s opens=%d\n",
                  lastTemp, lastHum, status.c_str(), doorState.c_str(), openCount);
  }

  // ── READ CONFIG from Firebase every 30 seconds ────────────────────────────
  // protection_mode + humidity thresholds + silica countdown — same cadence, same block.
  if (now - lastProtCheckMs > PROT_CHECK_INTERVAL && Firebase.ready()) {
    lastProtCheckMs = now;
    bool prevProtectionMode = protectionMode;
    if (Firebase.RTDB.getBool(&fbdo, String(FIREBASE_PATH) + "/protection_mode", &protectionMode)) {
      Serial.printf("[Firebase] protection_mode = %s\n", protectionMode ? "ON" : "OFF");
    }
    if (Firebase.RTDB.getFloat(&fbdo, String(FIREBASE_PATH) + "/warn_humidity", &warnHum)) {
      Serial.printf("[Firebase] warn_humidity = %.1f\n", warnHum);
    }
    if (Firebase.RTDB.getFloat(&fbdo, String(FIREBASE_PATH) + "/crit_humidity", &critHum)) {
      Serial.printf("[Firebase] crit_humidity = %.1f\n", critHum);
    }
    if (Firebase.RTDB.getInt(&fbdo, String(FIREBASE_PATH) + "/silica_days_left", &silicaDaysLeft)) {
      Serial.printf("[Firebase] silica_days_left = %d\n", silicaDaysLeft);
    }

    // Protection mode changed (or is ON) while the door is ALREADY open — the
    // buzzer only reacts to open/close transitions, so without this check,
    // turning protection ON after the door was already open would stay silent.
    if (protectionMode != prevProtectionMode) {
      if (protectionMode && doorOpenNow) {
        // Protection just turned ON and door is currently open — start alarming now.
        noTone(BUZZER_PIN);
        seqStep = 0;
        tone(BUZZER_PIN, ALARM_FREQ);
        buzzerMode = ALARM;
        buzzerMs   = now;
      } else if (!protectionMode && (buzzerMode == ALARM || buzzerMode == ALARM_PAUSE)) {
        // Protection just turned OFF mid-alarm — stop immediately.
        noTone(BUZZER_PIN);
        buzzerMode = SILENT;
      }
    }
    updateBuzzer();
  }
}
