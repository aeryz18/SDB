/**
 * DryBox AI — Wokwi Simulation Sketch
 *
 * Firebase is removed — Wokwi has no internet access.
 * Use this to verify hardware logic (DHT22, door button, OLED, buzzer).
 * Flash drybox_esp32.ino to the real ESP32 with Firebase credentials.
 */

#include <DHT.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ─── PINS ────────────────────────────────────────────────────────────────────
#define DHT_PIN       4
#define DHT_TYPE      DHT22
#define DOOR_PIN      15
#define BUZZER_PIN    2

#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT 64
#define OLED_RESET    -1
#define OLED_ADDRESS  0x3C

// ─── THRESHOLDS ──────────────────────────────────────────────────────────────
#define WARN_HUMIDITY  35.0
#define CRIT_HUMIDITY  45.0

// ─── GLOBALS ─────────────────────────────────────────────────────────────────
DHT dht(DHT_PIN, DHT_TYPE);
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

float  lastTemp     = 0.0;
float  lastHumidity = 0.0;
String lastDoor     = "closed";
String lastStatus   = "normal";

unsigned long lastReadMs = 0;
#define READ_INTERVAL 2000   // read every 2s in simulation

// ─── BUZZER STATE MACHINE ────────────────────────────────────────────────────
// ALARM  = door open  → rapid beeps, continuous while open
// CALM   = door just closed → descending tones played once
// SILENT = door closed, calm tones finished

enum BuzzerMode { SILENT, ALARM, ALARM_PAUSE, CALM };
BuzzerMode    buzzerMode   = SILENT;
bool          prevDoorOpen = false;
unsigned long buzzerMs     = 0;
int           calmStep     = 0;

// Alarm: continuous tone ON/OFF while door is open
#define ALARM_BEEP_MS    300   // ms tone plays
#define ALARM_PAUSE_MS   150   // ms silence between beeps
#define ALARM_FREQ      2000   // Hz — audible on most passive buzzers

// Calm: three descending notes played once when door closes
const int CALM_FREQS[]     = { 900, 650, 420 };
const int CALM_DURATIONS[] = { 150, 150, 250 };
const int CALM_GAP_MS      = 60;
#define   CALM_STEPS       3

// ─── SETUP ───────────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);

  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  dht.begin();

  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDRESS)) {
    Serial.println("OLED not found");
  }

  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);
  display.setTextSize(1);
  display.setCursor(20, 24);
  display.println("DryBox AI");
  display.setCursor(10, 38);
  display.println("Simulation Mode");
  display.display();
  delay(1500);

  Serial.println("DryBox AI — Simulation ready");
  Serial.println("Adjust DHT22 sliders to change temp/humidity");
  Serial.println("Click green button to open/close door");
}

// ─── BUZZER TICK (call every loop — non-blocking) ────────────────────────────
void updateBuzzer() {
  unsigned long now = millis();

  switch (buzzerMode) {

    case ALARM:
      // Beeping phase: play tone for ALARM_BEEP_MS
      if (now - buzzerMs >= ALARM_BEEP_MS) {
        noTone(BUZZER_PIN);
        buzzerMode = ALARM_PAUSE;
        buzzerMs   = now;
      }
      break;

    case ALARM_PAUSE:
      // Silence phase: wait ALARM_PAUSE_MS then beep again
      if (now - buzzerMs >= ALARM_PAUSE_MS) {
        // Only restart alarm if door is still open
        if (digitalRead(DOOR_PIN) == HIGH) {
          tone(BUZZER_PIN, ALARM_FREQ);
          buzzerMode = ALARM;
          buzzerMs   = now;
        } else {
          buzzerMode = SILENT;
        }
      }
      break;

    case CALM:
      // Current note finished — move to next
      if (now - buzzerMs >= (unsigned long)(CALM_DURATIONS[calmStep] + CALM_GAP_MS)) {
        noTone(BUZZER_PIN);
        calmStep++;
        if (calmStep < CALM_STEPS) {
          tone(BUZZER_PIN, CALM_FREQS[calmStep]);
          buzzerMs = now;
        } else {
          buzzerMode = SILENT;
        }
      }
      break;

    case SILENT:
    default:
      break;
  }
}

// ─── LOOP ────────────────────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  updateBuzzer();   // runs every loop tick — non-blocking

  if (now - lastReadMs >= READ_INTERVAL) {
    lastReadMs = now;

    // --- Read DHT22 ---
    float h = dht.readHumidity();
    float t = dht.readTemperature();

    if (isnan(h) || isnan(t)) {
      Serial.println("DHT22 read failed");
      return;
    }

    lastTemp     = t;
    lastHumidity = h;

    // --- Read door button ---
    bool doorOpen = (digitalRead(DOOR_PIN) == HIGH);
    lastDoor = doorOpen ? "open" : "closed";

    // --- Determine status ---
    if (h > CRIT_HUMIDITY) {
      lastStatus = "crit";
    } else if (h > WARN_HUMIDITY) {
      lastStatus = "warn";
    } else {
      lastStatus = "normal";
    }

    // --- Buzzer state transitions ---
    if (doorOpen && !prevDoorOpen) {
      // Door just opened → start alarm immediately
      noTone(BUZZER_PIN);
      tone(BUZZER_PIN, ALARM_FREQ);   // start beeping right away
      buzzerMode = ALARM;
      buzzerMs   = millis();
      Serial.println("[BUZZER] ALARM — door opened");
    } else if (!doorOpen && prevDoorOpen) {
      // Door just closed → play calm sound, first note starts immediately
      noTone(BUZZER_PIN);
      calmStep = 0;
      tone(BUZZER_PIN, CALM_FREQS[0]);  // first note plays right now
      buzzerMode = CALM;
      buzzerMs   = millis();
      Serial.println("[BUZZER] CALM — door closed");
    }
    prevDoorOpen = doorOpen;

    // --- Serial output (replaces Firebase push in simulation) ---
    Serial.printf("[READ] temp=%.1f°C  hum=%.1f%%  door=%s  status=%s\n",
                  t, h, lastDoor.c_str(), lastStatus.c_str());

    // --- Update OLED ---
    display.clearDisplay();
    display.setTextSize(1);
    display.setCursor(0, 0);
    display.println("-- DryBox AI Sim --");
    display.drawLine(0, 9, 127, 9, SSD1306_WHITE);

    display.setTextSize(2);
    display.setCursor(0, 14);
    display.printf("%.1fC", t);
    display.setCursor(0, 36);
    display.printf("%.1f%%", h);

    display.setTextSize(1);
    display.setCursor(80, 14);
    if (lastStatus == "crit")       display.println("!! CRIT");
    else if (lastStatus == "warn")  display.println("! WARN");
    else                            display.println("  OK");

    display.setCursor(80, 36);
    display.print("Door:");
    display.setCursor(80, 46);
    display.println(doorOpen ? "OPEN" : "CLOSED");

    display.display();
  }
}
