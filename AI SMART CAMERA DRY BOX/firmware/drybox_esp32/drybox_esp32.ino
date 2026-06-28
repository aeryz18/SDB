/**
 * DryBox AI — ESP32 Firmware v2
 *
 * Hardware:
 *   ESP32 DevKit V1, DHT22 (GPIO4), Door button (GPIO18),
 *   SSD1306 OLED I2C (SDA=21, SCL=22), Passive buzzer (GPIO2)
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
#define DOOR_PIN      15    // INPUT_PULLUP: LOW = door closed, HIGH = door open
#define BUZZER_PIN    2

// ─── OLED ────────────────────────────────────────────────────────────────────
#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT 64
#define OLED_ADDR     0x3C

// ─── HUMIDITY THRESHOLDS (match Laravel device_settings) ─────────────────────
#define WARN_HUM   35.0
#define CRIT_HUM   45.0

// ─── OBJECTS ─────────────────────────────────────────────────────────────────
FirebaseData     fbdo;
FirebaseAuth     auth;
FirebaseConfig   config;
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);
DHT              dht(DHTPIN, DHTTYPE);

// ─── DOOR STATE ──────────────────────────────────────────────────────────────
int           lastDoorReading = HIGH;
int           stableDoor      = HIGH;   // HIGH = open, LOW = closed
unsigned long doorDebounceMs  = 0;
#define DEBOUNCE_MS 50
int    openCount = 0;
String doorState = "closed";
bool   prevDoorOpen = false;

// ─── PROTECTION MODE (read from Firebase) ────────────────────────────────────
bool          protectionMode    = false;
unsigned long lastProtCheckMs   = 0;
#define PROT_CHECK_INTERVAL     30000   // re-read from Firebase every 30s

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
#define ALARM_FREQ      2000

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
unsigned long lastSendMs  = 0;
unsigned long lastBlinkMs = 0;
bool          blinkState  = true;

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
        if (digitalRead(DOOR_PIN) == HIGH) {   // still open → keep alarming
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
  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(BUZZER_PIN, OUTPUT);

  // Read real door state at boot — fixes initialization mismatch
  stableDoor      = digitalRead(DOOR_PIN);
  lastDoorReading = stableDoor;
  doorState       = (stableDoor == HIGH) ? "open" : "closed";
  Serial.printf("Boot door state: %s (GPIO%d reads %s)\n",
                doorState.c_str(), DOOR_PIN, stableDoor == HIGH ? "HIGH" : "LOW");

  // OLED
  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR)) {
    Serial.println("OLED failed");
    while (true);
  }
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

  // WiFi
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected: " + WiFi.localIP().toString());

  // Firebase
  config.api_key      = API_KEY;
  config.database_url = DATABASE_URL;
  Firebase.signUp(&config, &auth, "", "");
  Firebase.begin(&config, &auth);
  Firebase.reconnectWiFi(true);

  Serial.println("System ready");
  tone(BUZZER_PIN, 1000, 200);   // startup beep
}

// ─── LOOP ────────────────────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  updateBuzzer();

  // ── DHT22 ─────────────────────────────────────────────────────────────────
  float temp = dht.readTemperature();
  float hum  = dht.readHumidity();
  if (isnan(temp) || isnan(hum)) return;

  // ── DOOR BUTTON with debounce ──────────────────────────────────────────────
  int reading = digitalRead(DOOR_PIN);
  if (reading != lastDoorReading) doorDebounceMs = now;
  if ((now - doorDebounceMs) > DEBOUNCE_MS && reading != stableDoor) {
    stableDoor = reading;
    bool doorOpen = (stableDoor == HIGH);
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
      tone(BUZZER_PIN, CALM_FREQS[0]);
      buzzerMode = CALM;
      buzzerMs   = now;
    }
  }
  lastDoorReading = reading;

  // ── STATUS (matches Laravel thresholds) ───────────────────────────────────
  String status;
  if (hum > CRIT_HUM)      status = "crit";
  else if (hum > WARN_HUM) status = "warn";
  else                      status = "normal";

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

  display.setCursor(0, 14);
  display.print("Temp: ");
  display.print(temp, 1);
  display.print(" C");

  display.setCursor(0, 26);
  display.print("Hum : ");
  display.print(hum, 1);
  display.print(" %");

  display.setCursor(0, 38);
  display.print("Door: ");
  display.print(doorState);
  display.print(" (");
  display.print(openCount);
  display.print("x)");

  display.setCursor(0, 52);
  if (status == "crit" && blinkState) {
    display.print("!! HIGH HUMIDITY !!");
  } else if (status == "warn") {
    display.print("Status: WARN");
  } else {
    display.print("Status: OK");
  }

  display.display();

  // ── FIREBASE PUSH every 5 seconds ─────────────────────────────────────────
  if (now - lastSendMs > 5000 && Firebase.ready()) {
    lastSendMs = now;

    Firebase.RTDB.setFloat(&fbdo,  String(FIREBASE_PATH) + "/temperature", temp);
    Firebase.RTDB.setFloat(&fbdo,  String(FIREBASE_PATH) + "/humidity",    hum);
    Firebase.RTDB.setString(&fbdo, String(FIREBASE_PATH) + "/status",      status);
    Firebase.RTDB.setString(&fbdo, String(FIREBASE_PATH) + "/door",        doorState);
    Firebase.RTDB.setInt(&fbdo,    String(FIREBASE_PATH) + "/openCount",   openCount);

    Serial.printf("[Firebase] temp=%.1f hum=%.1f status=%s door=%s opens=%d\n",
                  temp, hum, status.c_str(), doorState.c_str(), openCount);
  }

  // ── READ protection_mode from Firebase every 30 seconds ───────────────────
  if (now - lastProtCheckMs > PROT_CHECK_INTERVAL && Firebase.ready()) {
    lastProtCheckMs = now;
    if (Firebase.RTDB.getBool(&fbdo, String(FIREBASE_PATH) + "/protection_mode", &protectionMode)) {
      Serial.printf("[Firebase] protection_mode = %s\n", protectionMode ? "ON" : "OFF");
    }
  }
}
