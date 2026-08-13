#include <HTTPClient.h>
#include <HardwareSerial.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include <TinyGPSPlus.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>

// ================== DEVICE CONFIG ==================
// const char *WIFI_SSID = "4G-UFI-1E47";
// const char *WIFI_PASS = "1234567890";

const char *WIFI_SSID = "NONE-1";
const char *WIFI_PASS = "7836787NONE";

// const char *CMS_BASE_URL = "http://103.108.201.24:8321/";
const char *CMS_BASE_URL = "http://192.168.1.8:8000";
const char *DEVICE_API_KEY = "rahasia";
const char *BOX_ID = "BOX-01";

// ================== TFT_eSPI ==================
// Pastikan library TFT_eSPI sudah dikonfigurasi dengan pin berikut:
// SCLK = GPIO18, MOSI = GPIO23, DC = GPIO27, RST = GPIO26, CS = -1 / di-ground.
TFT_eSPI tft = TFT_eSPI();
bool displayReady = false;
String lastDisplayTitle;
String lastDisplayLine1;
String lastDisplayLine2;
String lastDisplayLine3;
String lastDisplayFooter;
uint16_t lastDisplayAccentColor = 0;
uint16_t lastDisplayFooterColor = 0;

// ================== PIN ==================
constexpr int GM65_RX_PIN = 22;
constexpr int GM65_TX_PIN = 21;
constexpr int GPS_RX_PIN = 16;
constexpr int GPS_TX_PIN = 17;
constexpr int RELAY_PIN = 25;
constexpr int VOLTAGE_SENSOR_DOORLOCK_PIN = 34;
constexpr int VOLTAGE_SENSOR_DEVICE_PIN = 35;
constexpr int LDR_PIN = 32;

// ================== CONFIG ==================
constexpr bool RELAY_ACTIVE_HIGH = false;

constexpr uint32_t SERIAL_BAUD = 115200;
constexpr uint32_t GM65_BAUD = 9600;
constexpr uint32_t GPS_BAUD = 9600;

constexpr float VOLTAGE_DIVIDER_RATIO = 5.0f;

// Baterai Device (2S, 7.4V nominal)
constexpr float BATTERY_DEVICE_FULL_VOLTAGE = 8.40f;
constexpr float BATTERY_DEVICE_EMPTY_VOLTAGE = 6.60f;
constexpr float BATTERY_DEVICE_MAX_VALID_VOLTAGE = 9.20f;

// Baterai Doorlock (3S, 11.1V nominal)
constexpr float BATTERY_DOORLOCK_FULL_VOLTAGE = 12.60f;
constexpr float BATTERY_DOORLOCK_EMPTY_VOLTAGE = 9.60f;
constexpr float BATTERY_DOORLOCK_MAX_VALID_VOLTAGE = 13.50f;

float smoothedVoltageDoorlock = -1.0f;
float smoothedVoltageDevice = -1.0f;
constexpr float FILTER_ALPHA = 0.01f;
constexpr float BATTERY_DISCONNECTED_ADC_VOLTAGE = 0.05f;

// ---- FIX: debounce untuk deteksi "disconnected" ----
// Sebelumnya, satu kali baca noise yang melebihi ambang batas langsung
// dianggap "kabel dicabut" dan mereset filter EMA, sehingga percentage
// bisa melompat drastis (mis. 73% -> 80%) hanya karena 1 sample noise.
// Sekarang butuh beberapa kali bacaan berturut-turut di luar rentang
// valid baru dianggap benar-benar disconnect.
int doorlockOutOfRangeCount = 0;
int deviceOutOfRangeCount = 0;
constexpr int OUT_OF_RANGE_CONFIRM_COUNT = 5;

// Counter untuk adaptive alpha
int doorlockValidCount = 0;
int deviceValidCount = 0;

constexpr int LDR_CLOSED_THRESHOLD = 4035;

constexpr unsigned long WIFI_RETRY_DELAY_MS = 1000;
constexpr unsigned long WIFI_STABILIZE_MS = 2000;
constexpr unsigned long HTTP_RETRY_DELAY_MS = 1500;
constexpr unsigned long HEARTBEAT_INTERVAL_MS = 2000;
constexpr unsigned long GPS_SERIAL_LOG_INTERVAL_MS = 2000;
constexpr unsigned long QR_CHAR_TIMEOUT_MS = 80;
constexpr unsigned long SCREEN_REFRESH_MS = 1000;
constexpr unsigned long UNLOCK_TIMEOUT_MS = 30000;
constexpr unsigned long MANUAL_RELAY_DURATION_MS = 5000;
constexpr size_t GPS_SERIAL_RX_BUFFER_SIZE = 2048;

HardwareSerial barcodeSerial(1);
HardwareSerial gpsSerial(2);
TinyGPSPlus gps;

enum DeviceState {
  STATE_WAIT_QR,
  STATE_VALIDATE_QR,
  STATE_UNLOCKED,
  STATE_WAIT_CLOSE_QR,
};

struct ScanResponse {
  bool httpOk = false;
  bool valid = false;
  String message;
  String qrType;
  String action;
  String nextQrType;
};

DeviceState currentState = STATE_WAIT_QR;

String barcodeBuffer;
String activeQrCode;
String activeQrType;
String expectedCloseQrType;

float currentLat = 0.0f;
float currentLng = 0.0f;
bool gpsHasFix = false;

unsigned long lastHeartbeatAt = 0;
unsigned long lastGpsLogAt = 0;
unsigned long lastScreenRefreshAt = 0;
unsigned long lastQrCharAt = 0;
unsigned long unlockStartedAt = 0;
unsigned long wifiConnectedAt = 0;
unsigned long manualRelayOffAt = 0;
bool doorOpenedSinceUnlock = false;
bool relayIsUnlocked = false;
bool manualRelayActive = false;

String buildApiUrl(const char *path) {
  String url = CMS_BASE_URL;
  if (url.endsWith("/")) {
    url.remove(url.length() - 1);
  }

  return url + String(path);
}

String escapeJson(const String &value) {
  String escaped;
  escaped.reserve(value.length() + 8);

  for (size_t i = 0; i < value.length(); ++i) {
    const char c = value.charAt(i);
    if (c == '\\' || c == '"') {
      escaped += '\\';
    }
    escaped += c;
  }

  return escaped;
}

void setRelay(bool unlocked) {
  relayIsUnlocked = unlocked;
  const bool activeLevel = RELAY_ACTIVE_HIGH ? unlocked : !unlocked;
  digitalWrite(RELAY_PIN, activeLevel ? HIGH : LOW);
}

float readBatteryVoltage(int pin) {
  uint32_t totalMilliVolts = 0;
  constexpr int SAMPLES = 50;

  for (int i = 0; i < SAMPLES; i++) {
    totalMilliVolts += analogReadMilliVolts(pin);
    delayMicroseconds(500);
  }

  // Hitung rata-rata ADC dalam bentuk Volt
  float rawAdcVoltage = (totalMilliVolts / (float)SAMPLES) / 1000.0f;
  float currentRawVoltage = rawAdcVoltage * VOLTAGE_DIVIDER_RATIO;

  // ==========================================
  // LOGIKA PROTEKSI CABUT KABEL (DISCONNECTED)
  // ==========================================
  float maxValidVoltage = (pin == VOLTAGE_SENSOR_DOORLOCK_PIN)
                              ? BATTERY_DOORLOCK_MAX_VALID_VOLTAGE
                              : BATTERY_DEVICE_MAX_VALID_VOLTAGE;

  const bool outOfRange = (rawAdcVoltage <= BATTERY_DISCONNECTED_ADC_VOLTAGE ||
                           currentRawVoltage > maxValidVoltage);

  int &outOfRangeCount = (pin == VOLTAGE_SENSOR_DOORLOCK_PIN)
                             ? doorlockOutOfRangeCount
                             : deviceOutOfRangeCount;

  if (outOfRange) {
    outOfRangeCount++;

    // FIX: satu / beberapa sample noise saja belum tentu berarti kabel
    // dicabut. Selama belum mencapai OUT_OF_RANGE_CONFIRM_COUNT kali
    // berturut-turut, anggap ini noise sesaat dan kembalikan nilai
    // smoothed terakhir yang masih valid (bukan langsung 0V / reset).
    if (outOfRangeCount < OUT_OF_RANGE_CONFIRM_COUNT) {
      if (pin == VOLTAGE_SENSOR_DOORLOCK_PIN && smoothedVoltageDoorlock >= 0) {
        return smoothedVoltageDoorlock;
      }
      if (pin == VOLTAGE_SENSOR_DEVICE_PIN && smoothedVoltageDevice >= 0) {
        return smoothedVoltageDevice;
      }
      return 0.0f;
    }

    // Sudah konsisten out-of-range beberapa kali berturut-turut -> baru
    // dianggap benar-benar disconnect. RESET memori filter ke -1.0f agar
    // saat dicolok lagi bisa langsung normal.
    if (pin == VOLTAGE_SENSOR_DOORLOCK_PIN) {
      smoothedVoltageDoorlock = -1.0f;
    } else if (pin == VOLTAGE_SENSOR_DEVICE_PIN) {
      smoothedVoltageDevice = -1.0f;
    }

    // Kembalikan 0V agar fungsi persentase menampilkan 0%
    return 0.0f;
  }

  // Reading normal (dalam rentang valid) -> reset counter out-of-range
  outOfRangeCount = 0;

  // ==========================================
  // LOGIKA FILTER EMA (SMOOTHING) DENGAN ADAPTIVE ALPHA
  // ==========================================
  if (pin == VOLTAGE_SENSOR_DOORLOCK_PIN) {
    // Jika memori reset (-1.0), langsung pakai nilai asli tanpa delay
    if (smoothedVoltageDoorlock < 0) {
      smoothedVoltageDoorlock = currentRawVoltage;
      doorlockValidCount = 1;
    } else {
      // Adaptive alpha: 10 sampel pertama pakai alpha 0.2 (20%) agar cepat mendekati nilai asli
      // Setelah itu pakai FILTER_ALPHA (1%) agar sangat stabil
      float currentAlpha = (doorlockValidCount < 10) ? 0.2f : FILTER_ALPHA;
      
      // Jika memori ada, perhalus pergerakannya
      smoothedVoltageDoorlock =
          (currentAlpha * currentRawVoltage) +
          ((1.0f - currentAlpha) * smoothedVoltageDoorlock);
          
      if (doorlockValidCount < 10) {
        doorlockValidCount++;
      }
    }
    return smoothedVoltageDoorlock;
  } else if (pin == VOLTAGE_SENSOR_DEVICE_PIN) {
    if (smoothedVoltageDevice < 0) {
      smoothedVoltageDevice = currentRawVoltage;
      deviceValidCount = 1;
    } else {
      float currentAlpha = (deviceValidCount < 10) ? 0.2f : FILTER_ALPHA;
      
      smoothedVoltageDevice = (currentAlpha * currentRawVoltage) +
                              ((1.0f - currentAlpha) * smoothedVoltageDevice);
                              
      if (deviceValidCount < 10) {
        deviceValidCount++;
      }
    }
    return smoothedVoltageDevice;
  }

  return currentRawVoltage;
}

uint8_t batteryPercentFromVoltage(float voltage, float emptyVoltage,
                                  float fullVoltage) {
  if (voltage <= emptyVoltage) {
    return 0;
  }

  if (voltage >= fullVoltage) {
    return 100;
  }

  return static_cast<uint8_t>(((voltage - emptyVoltage) * 100.0f) /
                              (fullVoltage - emptyVoltage));
}

uint8_t readDoorlockBatteryPercent() {
  return batteryPercentFromVoltage(
      readBatteryVoltage(VOLTAGE_SENSOR_DOORLOCK_PIN),
      BATTERY_DOORLOCK_EMPTY_VOLTAGE, BATTERY_DOORLOCK_FULL_VOLTAGE);
}

uint8_t readDeviceBatteryPercent() {
  return batteryPercentFromVoltage(
      readBatteryVoltage(VOLTAGE_SENSOR_DEVICE_PIN),
      BATTERY_DEVICE_EMPTY_VOLTAGE, BATTERY_DEVICE_FULL_VOLTAGE);
}

int readLdrValue() { return analogRead(LDR_PIN); }

bool isDoorClosed() { return readLdrValue() > LDR_CLOSED_THRESHOLD; }

const char *doorStatusLabel() { return isDoorClosed() ? "Closed" : "Open"; }

const char *relayStatusLabel() { return relayIsUnlocked ? "On" : "Off"; }

void startManualRelay(unsigned long durationMs) {
  if (durationMs == 0) {
    durationMs = MANUAL_RELAY_DURATION_MS;
  }

  manualRelayActive = true;
  manualRelayOffAt = millis() + durationMs;
  setRelay(true);

  Serial.print("[RELAY] Manual ON for ");
  Serial.print(durationMs);
  Serial.println(" ms");
}

void serviceManualRelay() {
  if (!manualRelayActive) {
    return;
  }

  if ((long)(millis() - manualRelayOffAt) < 0) {
    return;
  }

  manualRelayActive = false;
  if (currentState != STATE_UNLOCKED) {
    setRelay(false);
  }

  Serial.println("[RELAY] Manual OFF");
}

void updateGps() {
  while (gpsSerial.available() > 0) {
    gps.encode(gpsSerial.read());
  }

  if (gps.location.isValid()) {
    currentLat = gps.location.lat();
    currentLng = gps.location.lng();
    gpsHasFix = true;
  }
}

void serviceBackground(unsigned long durationMs) {
  const unsigned long startedAt = millis();
  while (millis() - startedAt < durationMs) {
    updateGps();
    delay(10);
  }
}

void logGpsToSerial() {
  if (millis() - lastGpsLogAt < GPS_SERIAL_LOG_INTERVAL_MS) {
    return;
  }

  lastGpsLogAt = millis();

  Serial.print("[GPS] fix: ");
  Serial.print(gps.location.isValid() ? "yes" : "no");
  Serial.print(" | sat: ");
  Serial.print(gps.satellites.isValid() ? gps.satellites.value() : 0);
  Serial.print(" | chars: ");
  Serial.print(gps.charsProcessed());

  if (gps.location.isValid()) {
    Serial.print(" | lat: ");
    Serial.print(gps.location.lat(), 6);
    Serial.print(" | lng: ");
    Serial.println(gps.location.lng(), 6);
    return;
  }

  Serial.println(" | lat/lng: waiting");
}

bool ensureWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  Serial.println("Connecting to WiFi...");
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  int retry = 0;
  while (WiFi.status() != WL_CONNECTED) {
    serviceBackground(WIFI_RETRY_DELAY_MS);
    Serial.print(".");
    retry++;
    if (retry > 20) {
      Serial.println("\nFailed to connect!");
      return false;
    }
  }

  Serial.println("\nWiFi Connected!");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());

  wifiConnectedAt = millis();
  Serial.println("Waiting before network...");
  serviceBackground(WIFI_STABILIZE_MS);

  return true;
}

int postJson(const char *path, const String &payload, String &response) {
  response = "";

  if (!ensureWiFi()) {
    return -1;
  }

  const String url = buildApiUrl(path);
  if (wifiConnectedAt > 0 && millis() - wifiConnectedAt < WIFI_STABILIZE_MS) {
    serviceBackground(WIFI_STABILIZE_MS - (millis() - wifiConnectedAt));
  }

  int lastCode = -1;
  String lastResponse;

  for (int attempt = 1; attempt <= 3; attempt++) {
    Serial.println("[HTTP] POST " + url);
    Serial.print("[HTTP] Attempt: ");
    Serial.println(attempt);
    const bool isHttps = url.startsWith("https://");

    if (isHttps) {
      WiFiClientSecure client;
      client.setInsecure();

      HTTPClient http;
      if (!http.begin(client, url)) {
        Serial.println("[HTTP] http.begin failed");
        lastCode = -2;
        serviceBackground(HTTP_RETRY_DELAY_MS);
        continue;
      }

      http.addHeader("Content-Type", "application/json");
      http.addHeader("ngrok-skip-browser-warning", "true");
      if (strlen(DEVICE_API_KEY) > 0) {
        http.addHeader("X-Device-Key", DEVICE_API_KEY);
      }

      http.setConnectTimeout(8000);
      http.setTimeout(12000);

      Serial.println("[HTTP] Sending...");
      lastCode = http.POST(payload);
      lastResponse = http.getString();

      Serial.print("[HTTP] Code: ");
      Serial.println(lastCode);
      Serial.println("[HTTP] Response:");
      Serial.println(lastResponse);

      if (lastCode <= 0) {
        Serial.print("[HTTP] Error: ");
        Serial.println(http.errorToString(lastCode));
        http.end();
        serviceBackground(HTTP_RETRY_DELAY_MS);
        continue;
      }

      http.end();
      response = lastResponse;
      return lastCode;
    } else {
      WiFiClient client;
      HTTPClient http;
      if (!http.begin(client, url)) {
        Serial.println("[HTTP] http.begin failed");
        lastCode = -2;
        serviceBackground(HTTP_RETRY_DELAY_MS);
        continue;
      }

      http.addHeader("Content-Type", "application/json");
      http.addHeader("ngrok-skip-browser-warning", "true");
      if (strlen(DEVICE_API_KEY) > 0) {
        http.addHeader("X-Device-Key", DEVICE_API_KEY);
      }

      http.setConnectTimeout(8000);
      http.setTimeout(12000);

      Serial.println("[HTTP] Sending...");
      lastCode = http.POST(payload);
      lastResponse = http.getString();

      Serial.print("[HTTP] Code: ");
      Serial.println(lastCode);
      Serial.println("[HTTP] Response:");
      Serial.println(lastResponse);

      if (lastCode <= 0) {
        Serial.print("[HTTP] Error: ");
        Serial.println(http.errorToString(lastCode));
        http.end();
        serviceBackground(HTTP_RETRY_DELAY_MS);
        continue;
      }

      http.end();
      response = lastResponse;
      return lastCode;
    }
  }

  response = lastResponse;
  return lastCode;
}

String extractJsonValue(const String &json, const char *key) {
  const String needle = String("\"") + key + "\":";
  const int keyPos = json.indexOf(needle);
  if (keyPos < 0) {
    return "";
  }

  const bool quoted = keyPos + needle.length() < json.length() &&
                      json.charAt(keyPos + needle.length()) == '"';
  int valuePos = keyPos + needle.length();

  while (valuePos < json.length() &&
         (json.charAt(valuePos) == ' ' || json.charAt(valuePos) == '"')) {
    valuePos++;
  }

  int endPos = valuePos;
  if (quoted) {
    endPos = json.indexOf('"', valuePos);
  } else {
    while (endPos < json.length() && json.charAt(endPos) != ',' &&
           json.charAt(endPos) != '}') {
      endPos++;
    }
  }

  if (endPos < 0 || endPos <= valuePos) {
    return "";
  }

  return json.substring(valuePos, endPos);
}

void drawCenteredText(const String &text, int y, uint8_t size, uint16_t color) {
  if (!displayReady) {
    return;
  }

  tft.setTextSize(size);
  tft.setTextColor(color, TFT_BLACK);
  tft.setTextDatum(MC_DATUM);
  tft.drawString(text, 120, y);
}

void drawScreen(const String &title, const String &line1, const String &line2,
                const String &line3, uint16_t accentColor) {
  if (!displayReady) {
    return;
  }

  tft.fillScreen(TFT_BLACK);
  drawCenteredText("SAFETY BOX", 16, 2, TFT_WHITE);
  drawCenteredText(String("ID: ") + BOX_ID, 38, 1, TFT_CYAN);
  tft.drawFastHLine(0, 52, 240, accentColor);
  drawCenteredText(title, 74, 2, accentColor);
  drawCenteredText(line1, 112, 1, TFT_WHITE);
  drawCenteredText(line2, 136, 1, TFT_WHITE);
  drawCenteredText(line3, 160, 1, TFT_WHITE);
}

void resetDisplayCache() {
  lastDisplayTitle = "";
  lastDisplayLine1 = "";
  lastDisplayLine2 = "";
  lastDisplayLine3 = "";
  lastDisplayFooter = "";
  lastDisplayAccentColor = 0;
  lastDisplayFooterColor = 0;
}

void drawScreenIfChanged(const String &title, const String &line1,
                         const String &line2, const String &line3,
                         uint16_t accentColor, const String &footer = "",
                         uint16_t footerColor = TFT_WHITE) {
  if (lastDisplayTitle == title && lastDisplayLine1 == line1 &&
      lastDisplayLine2 == line2 && lastDisplayLine3 == line3 &&
      lastDisplayFooter == footer && lastDisplayAccentColor == accentColor &&
      lastDisplayFooterColor == footerColor) {
    return;
  }

  drawScreen(title, line1, line2, line3, accentColor);
  if (footer.length() > 0) {
    drawCenteredText(footer, 192, 1, footerColor);
  }

  lastDisplayTitle = title;
  lastDisplayLine1 = line1;
  lastDisplayLine2 = line2;
  lastDisplayLine3 = line3;
  lastDisplayFooter = footer;
  lastDisplayAccentColor = accentColor;
  lastDisplayFooterColor = footerColor;
}

void refreshIdleScreen() {
  const String wifiState =
      WiFi.status() == WL_CONNECTED ? "WiFi OK" : "WiFi OFF";
  const String gpsState =
      gpsHasFix ? String(currentLat, 5) + "," + String(currentLng, 5)
                : "GPS belum fix";
  const String batteryState =
      "Batt Door:" + String(readDoorlockBatteryPercent()) +
      "% Device:" + String(readDeviceBatteryPercent()) + "%";
  const String doorState = isDoorClosed() ? "Door: Tertutup" : "Door: Terbuka";

  drawScreenIfChanged("SCAN QR", wifiState, batteryState, gpsState, TFT_YELLOW,
                      doorState, TFT_GREEN);
}

void refreshUnlockedScreen() {
  const String stage = activeQrType.length() > 0 ? activeQrType : activeQrCode;
  const String doorState =
      isDoorClosed() ? "Door masih tertutup" : "Door terbuka";
  drawScreenIfChanged("UNLOCK", "Akses diberikan", stage, doorState, TFT_GREEN);
}

void refreshWaitCloseQrScreen() {
  const String qrPrompt =
      expectedCloseQrType.length() > 0 ? expectedCloseQrType : "Scan QR closed";
  const String doorState =
      isDoorClosed() ? "Door sudah tertutup" : "Tutup box dulu";
  drawScreenIfChanged("SCAN CLOSED", qrPrompt, doorState, "Lanjutkan proses",
                      TFT_YELLOW);
}

String buildHeartbeatPayload(const char *status) {
  String payload = "{";
  payload += "\"box_id\":\"" + escapeJson(String(BOX_ID)) + "\",";
  payload +=
      "\"battery_doorlock\":" + String(readDoorlockBatteryPercent()) + ",";
  payload += "\"battery_device\":" + String(readDeviceBatteryPercent()) + ",";
  payload += "\"status\":\"" + String(status) + "\",";
  payload += "\"door_status\":\"" + String(doorStatusLabel()) + "\",";
  payload += "\"relay_status\":\"" + String(relayStatusLabel()) + "\"";

  if (gpsHasFix) {
    payload += ",\"lat\":" + String(currentLat, 6);
    payload += ",\"lng\":" + String(currentLng, 6);
  }

  payload += "}";
  return payload;
}

bool sendHeartbeat(const char *status) {
  String response;
  const int code = postJson("/api/device/heartbeat",
                            buildHeartbeatPayload(status), response);
  if (code <= 0) {
    return false;
  }

  const String relayAction = extractJsonValue(response, "action");
  if (relayAction == "turn_on_relay") {
    unsigned long durationMs =
        extractJsonValue(response, "duration_ms").toInt();
    if (durationMs == 0) {
      durationMs = MANUAL_RELAY_DURATION_MS;
    }

    startManualRelay(durationMs);
  }

  return true;
}

ScanResponse sendScanRequest(const String &qrCode) {
  ScanResponse result;

  String payload = "{";
  payload += "\"box_id\":\"" + escapeJson(String(BOX_ID)) + "\",";
  payload += "\"qr_code\":\"" + escapeJson(qrCode) + "\"";

  if (gpsHasFix) {
    payload += ",\"lat\":" + String(currentLat, 6);
    payload += ",\"lng\":" + String(currentLng, 6);
  }

  payload += "}";

  String response;
  const int code = postJson("/api/device/scan", payload, response);

  result.httpOk = code > 0;
  if (!result.httpOk) {
    result.message = "HTTP gagal";
    return result;
  }

  result.valid = response.indexOf("\"result\":\"valid\"") >= 0;
  result.message = extractJsonValue(response, "message");
  result.qrType = extractJsonValue(response, "qr_type");
  result.action = extractJsonValue(response, "action");
  result.nextQrType = extractJsonValue(response, "next_qr_type");

  if (!result.valid && result.message.length() == 0) {
    result.message = "QR ditolak";
  }

  return result;
}

const char *heartbeatStatusForState() {
  if (currentState == STATE_UNLOCKED || currentState == STATE_WAIT_CLOSE_QR) {
    return "In Use";
  }

  return "Available";
}

void maybeSendHeartbeat() {
  if (millis() - lastHeartbeatAt < HEARTBEAT_INTERVAL_MS) {
    return;
  }

  lastHeartbeatAt = millis();
  sendHeartbeat(heartbeatStatusForState());
}

void beginScanValidation(const String &qrValue) {
  activeQrCode = qrValue;
  currentState = STATE_VALIDATE_QR;
}

void resetWorkflow() {
  activeQrCode = "";
  activeQrType = "";
  expectedCloseQrType = "";
  doorOpenedSinceUnlock = false;
  currentState = STATE_WAIT_QR;
}

void readBarcode() {
  if (currentState != STATE_WAIT_QR && currentState != STATE_WAIT_CLOSE_QR) {
    while (barcodeSerial.available()) {
      barcodeSerial.read();
    }
    return;
  }

  while (barcodeSerial.available()) {
    const char c = static_cast<char>(barcodeSerial.read());
    lastQrCharAt = millis();

    if (c == '\r' || c == '\n') {
      barcodeBuffer.trim();
      if (barcodeBuffer.length() > 0) {
        beginScanValidation(barcodeBuffer);
        barcodeBuffer = "";
        return;
      }
      continue;
    }

    barcodeBuffer += c;
  }

  if (barcodeBuffer.length() > 0 &&
      millis() - lastQrCharAt > QR_CHAR_TIMEOUT_MS) {
    barcodeBuffer.trim();
    if (barcodeBuffer.length() > 0) {
      beginScanValidation(barcodeBuffer);
    }
    barcodeBuffer = "";
  }
}

void setup() {
  Serial.begin(SERIAL_BAUD);
  serviceBackground(250);
  Serial.println("[BOOT] setup start");

  pinMode(RELAY_PIN, OUTPUT);
  setRelay(false);
  pinMode(LDR_PIN, INPUT);
  Serial.println("[BOOT] gpio ok");

  analogReadResolution(12);
  analogSetPinAttenuation(VOLTAGE_SENSOR_DOORLOCK_PIN, ADC_11db);
  analogSetPinAttenuation(VOLTAGE_SENSOR_DEVICE_PIN, ADC_11db);
  analogSetPinAttenuation(LDR_PIN, ADC_11db);
  Serial.println("[BOOT] adc ok");

  gpsSerial.setRxBufferSize(GPS_SERIAL_RX_BUFFER_SIZE);
  barcodeSerial.begin(GM65_BAUD, SERIAL_8N1, GM65_RX_PIN, GM65_TX_PIN);
  gpsSerial.begin(GPS_BAUD, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
  Serial.println("[BOOT] serial peripherals ok");

  tft.begin();
  tft.setRotation(0);
  displayReady = true;
  resetDisplayCache();
  drawScreen("BOOT", "Init device", "Connecting WiFi", "Please wait", TFT_CYAN);
  Serial.println("[BOOT] tft ok");

  currentState = STATE_WAIT_QR;
  lastHeartbeatAt = millis() - HEARTBEAT_INTERVAL_MS;
  lastScreenRefreshAt = 0;
}

void loop() {
  updateGps();
  logGpsToSerial();
  readBarcode();
  maybeSendHeartbeat();
  serviceManualRelay();

  switch (currentState) {
  case STATE_WAIT_QR:
    // Low-battery flow sementara dimatikan.
    if (millis() - lastScreenRefreshAt >= SCREEN_REFRESH_MS) {
      lastScreenRefreshAt = millis();
      refreshIdleScreen();
    }
    break;

  case STATE_VALIDATE_QR: {
    const bool awaitingCloseQr = expectedCloseQrType.length() > 0;
    resetDisplayCache();
    drawScreen("VALIDATE", activeQrCode, "Kirim ke CMS", "Mohon tunggu",
               TFT_CYAN);

    if (awaitingCloseQr && !isDoorClosed()) {
      resetDisplayCache();
      drawScreen("TUTUP BOX", "Scan closed ditolak", "Pintu masih terbuka",
                 "Tutup box dulu", TFT_RED);
      serviceBackground(1800);
      currentState = STATE_WAIT_CLOSE_QR;
      break;
    }

    const ScanResponse response = sendScanRequest(activeQrCode);
    if (!response.httpOk) {
      resetDisplayCache();
      drawScreen("ERROR", "Gagal akses API", "Cek WiFi / URL", response.message,
                 TFT_RED);
      serviceBackground(1800);
      currentState = awaitingCloseQr ? STATE_WAIT_CLOSE_QR : STATE_WAIT_QR;
      break;
    }

    if (!response.valid) {
      resetDisplayCache();
      drawScreen("DITOLAK", activeQrCode, response.message, "Scan QR lain",
                 TFT_RED);
      serviceBackground(1800);
      currentState = awaitingCloseQr ? STATE_WAIT_CLOSE_QR : STATE_WAIT_QR;
      break;
    }

    activeQrType = response.qrType;

    if (response.action == "unlock") {
      expectedCloseQrType = response.nextQrType;
      unlockStartedAt = millis();
      doorOpenedSinceUnlock = !isDoorClosed();
      setRelay(true);
      currentState = STATE_UNLOCKED;
      refreshUnlockedScreen();
      break;
    }

    if (response.action == "complete") {
      setRelay(false);
      resetDisplayCache();
      drawScreen("SELESAI", response.qrType, response.message,
                 "Kembali standby", TFT_GREEN);
      serviceBackground(1800);
      resetWorkflow();
      break;
    }

    resetDisplayCache();
    drawScreen("ERROR", "Action API invalid", response.action, "Cek backend",
               TFT_RED);
    serviceBackground(1800);
    currentState = awaitingCloseQr ? STATE_WAIT_CLOSE_QR : STATE_WAIT_QR;
    break;
  }

  case STATE_UNLOCKED: {
    if (millis() - lastScreenRefreshAt >= SCREEN_REFRESH_MS) {
      lastScreenRefreshAt = millis();
      refreshUnlockedScreen();
    }

    const bool doorClosed = isDoorClosed();
    if (!doorClosed) {
      doorOpenedSinceUnlock = true;
    }

    if ((doorOpenedSinceUnlock && doorClosed) ||
        millis() - unlockStartedAt >= UNLOCK_TIMEOUT_MS) {
      setRelay(false);
      currentState = STATE_WAIT_CLOSE_QR;
      refreshWaitCloseQrScreen();
    }
    break;
  }

  case STATE_WAIT_CLOSE_QR:
    if (millis() - lastScreenRefreshAt >= SCREEN_REFRESH_MS) {
      lastScreenRefreshAt = millis();
      refreshWaitCloseQrScreen();
    }
    break;
  }

  delay(10);
}
