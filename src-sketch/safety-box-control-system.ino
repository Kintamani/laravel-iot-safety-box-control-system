#include <Adafruit_GFX.h>
#include <Adafruit_ST7789.h>
#include <HardwareSerial.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <WiFi.h>

// ================== DEVICE CONFIG ==================
const char *WIFI_SSID = "Your SSID";
const char *WIFI_PASS = "Your WIFI Password";

const char *CMS_BASE_URL = "http://your-cms-url.com";
const char *DEVICE_API_KEY = "Your Device API Key";

const char *BOX_ID = "BOX-01";

// ================== LCD ST7789 ==================
#define TFT_CS -1
#define TFT_DC 27
#define TFT_RST 26
#define TFT_SCLK 18
#define TFT_MOSI 23
#define TFT_MISO -1

Adafruit_ST7789 tft = Adafruit_ST7789(TFT_CS, TFT_DC, TFT_RST);

// ================== LCD UI ==================
const int BAT_W = 46;
const int BAT_H = 14;
const int BAT_X = 240 - BAT_W - 6;
const int BAT_Y = 6;

// ================== GM65 SETUP ==================
HardwareSerial QRSerial(2);

#define GM65_RX 34
#define GM65_TX 32

// ================== HARDWARE PIN ==================
#define RELAY_PIN 25
#define RELAY_ACTIVE_HIGH false

// ================== CONFIG ==================
const uint32_t SERIAL_BAUD = 115200;
const uint32_t GM65_BAUD = 9600;
const unsigned long MSG_TIMEOUT_MS = 80;
const unsigned long HEARTBEAT_INTERVAL_MS = 10000;

// ================== ROUTE SIMULATION ==================
struct RoutePoint
{
    float lat;
    float lng;
};

const RoutePoint routeOut[] = {
    {-6.910070f, 107.612600f},
    {-6.909000f, 107.614500f},
    {-6.914300f, 107.608900f},
    {-6.922200f, 107.610800f},
    {-6.929300f, 107.620500f},
    {-6.925400f, 107.636900f},
    {-6.925600f, 107.636700f},
};

const RoutePoint routeBack[] = {
    {-6.925600f, 107.636700f},
    {-6.925400f, 107.636900f},
    {-6.929300f, 107.620500f},
    {-6.922200f, 107.610800f},
    {-6.914300f, 107.608900f},
    {-6.909000f, 107.614500f},
    {-6.910070f, 107.612600f},
};

const size_t routeOutCount = sizeof(routeOut) / sizeof(routeOut[0]);
const size_t routeBackCount = sizeof(routeBack) / sizeof(routeBack[0]);

bool goingOut = true;
size_t routeIndex = 0;
float currentLat = routeOut[0].lat;
float currentLng = routeOut[0].lng;

// ================== BATTERY SIMULATION ==================
uint8_t simulatedBattery = 100;
bool batteryResetPending = false;
unsigned long lastBatteryTick = 0;
unsigned long batteryResetAt = 0;

// ================== STATE ==================
enum DeviceState
{
    STATE_INIT,
    STATE_WAIT_QR,
    STATE_VALIDATE,
    STATE_UNLOCK,
    STATE_LOCK,
};

DeviceState state = STATE_LOCK;
unsigned long lastHeartbeatAt = 0;

String barcodeBuffer = "";
String lastQr = "";
unsigned long lastCharTime = 0;
uint8_t batteryPercentCached = 100;

void setRelay(bool on)
{
    digitalWrite(RELAY_PIN, RELAY_ACTIVE_HIGH ? (on ? HIGH : LOW) : (on ? LOW : HIGH));
}

void drawCenteredText(const String &text, int16_t y, uint8_t size, uint16_t color)
{
    int16_t x1, y1;
    uint16_t w, h;
    tft.setTextSize(size);
    tft.getTextBounds(text, 0, 0, &x1, &y1, &w, &h);
    int16_t x = (240 - w) / 2;
    tft.setCursor(x, y);
    tft.setTextColor(color, ST77XX_BLACK);
    tft.print(text);
}

void drawBatteryIcon(uint8_t percent)
{
    uint16_t fillColor = ST77XX_GREEN;
    if (percent <= 20)
        fillColor = ST77XX_RED;
    else if (percent <= 50)
        fillColor = ST77XX_YELLOW;

    tft.drawRect(BAT_X, BAT_Y, BAT_W, BAT_H, ST77XX_WHITE);
    tft.fillRect(BAT_X + BAT_W, BAT_Y + 4, 3, BAT_H - 8, ST77XX_WHITE);
    tft.fillRect(BAT_X + 1, BAT_Y + 1, BAT_W - 2, BAT_H - 2, ST77XX_BLACK);

    int level = (BAT_W - 4) * percent / 100;
    if (level < 0)
        level = 0;
    tft.fillRect(BAT_X + 2, BAT_Y + 2, level, BAT_H - 4, fillColor);
}

void drawHeader()
{
    tft.fillScreen(ST77XX_BLACK);
    drawCenteredText("SAFETY BOX", 6, 2, ST77XX_WHITE);
    drawCenteredText(String("ID: ") + BOX_ID, 26, 1, ST77XX_CYAN);
    drawBatteryIcon(batteryPercentCached);
    tft.drawLine(0, 42, 240, 42, ST77XX_ORANGE);
}

void showMessage(const String &title, const String &subtitle = "")
{
    batteryPercentCached = simulatedBattery;
    drawHeader();
    drawCenteredText(title, 50, 1, ST77XX_YELLOW);
    if (subtitle.length() > 0)
        drawCenteredText(subtitle, 90, 2, ST77XX_WHITE);
}

void connectWiFi()
{
    if (WiFi.status() == WL_CONNECTED)
        return;

    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);

    unsigned long started = millis();
    showMessage("WiFi", "Connecting...");
    while (WiFi.status() != WL_CONNECTED && millis() - started < 10000)
    {
        delay(200);
    }
}

void advanceRoute()
{
    const RoutePoint *route = goingOut ? routeOut : routeBack;
    size_t count = goingOut ? routeOutCount : routeBackCount;

    currentLat = route[routeIndex].lat;
    currentLng = route[routeIndex].lng;

    routeIndex++;
    if (routeIndex >= count)
    {
        routeIndex = 0;
        goingOut = !goingOut;
    }
}

void updateSimBattery()
{
    unsigned long now = millis();

    if (batteryResetPending)
    {
        if (now >= batteryResetAt)
        {
            simulatedBattery = 100;
            batteryResetPending = false;
            lastBatteryTick = now;
        }
        return;
    }

    if (now - lastBatteryTick >= 10000)
    {
        lastBatteryTick = now;
        if (simulatedBattery > 10)
        {
            simulatedBattery = simulatedBattery - 10;
        }
        if (simulatedBattery <= 10)
        {
            simulatedBattery = 10;
            batteryResetPending = true;
            batteryResetAt = now + 10000;
        }
    }
}

bool sendHeartbeat(uint8_t batteryLevel, float lat, float lng, const char *status)
{
    connectWiFi();
    if (WiFi.status() != WL_CONNECTED)
        return false;

    HTTPClient http;
    String url = String(CMS_BASE_URL) + "/api/device/heartbeat";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    if (strlen(DEVICE_API_KEY) > 0)
        http.addHeader("X-Device-Key", DEVICE_API_KEY);

    String payload = "{";
    payload += "\"box_id\":\"" + String(BOX_ID) + "\",";
    payload += "\"battery_level\":" + String(batteryLevel) + ",";
    payload += "\"lat\":" + String(lat, 6) + ",";
    payload += "\"lng\":" + String(lng, 6) + ",";
    payload += "\"status\":\"" + String(status) + "\"";
    payload += "}";

    int code = http.POST(payload);
    http.end();
    return code > 0;
}

void tickHeartbeat()
{
    unsigned long now = millis();
    if (now - lastHeartbeatAt >= HEARTBEAT_INTERVAL_MS)
    {
        lastHeartbeatAt = now;
        advanceRoute();
        sendHeartbeat(simulatedBattery, currentLat, currentLng, "Available");
    }
}

bool sendLockEvent()
{
    connectWiFi();
    if (WiFi.status() != WL_CONNECTED)
        return false;

    HTTPClient http;
    String url = String(CMS_BASE_URL) + "/api/device/lock";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    if (strlen(DEVICE_API_KEY) > 0)
        http.addHeader("X-Device-Key", DEVICE_API_KEY);

    String payload = "{";
    payload += "\"box_id\":\"" + String(BOX_ID) + "\",";
    payload += "\"qr_code\":\"" + lastQr + "\"";
    payload += "}";

    int code = http.POST(payload);
    http.end();
    return code > 0;
}

bool sendScan(String qrCode, bool &valid, String &message)
{
    valid = false;
    message = "";

    connectWiFi();
    if (WiFi.status() != WL_CONNECTED)
    {
        message = "WiFi failed";
        return false;
    }

    HTTPClient http;
    String url = String(CMS_BASE_URL) + "/api/device/scan";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    if (strlen(DEVICE_API_KEY) > 0)
        http.addHeader("X-Device-Key", DEVICE_API_KEY);

    String payload = "{";
    payload += "\"box_id\":\"" + String(BOX_ID) + "\",";
    payload += "\"qr_code\":\"" + qrCode + "\",";
    payload += "\"lat\":" + String(currentLat, 6) + ",";
    payload += "\"lng\":" + String(currentLng, 6);
    payload += "}";

    int code = http.POST(payload);
    String response = http.getString();
    http.end();

    if (code <= 0)
    {
        message = "HTTP error";
        return false;
    }

    if (response.indexOf("\"result\":\"valid\"") >= 0)
    {
        valid = true;
        return true;
    }

    int msgIndex = response.indexOf("\"message\":\"");
    if (msgIndex >= 0)
    {
        int start = msgIndex + 11;
        int end = response.indexOf("\"", start);
        if (end > start)
            message = response.substring(start, end);
    }
    if (message.length() == 0)
        message = "QR denied";
    return true;
}

void processBarcode()
{
    if (barcodeBuffer.length() == 0)
        return;

    barcodeBuffer.trim();
    lastQr = barcodeBuffer;
    barcodeBuffer = "";
    if (lastQr.equalsIgnoreCase("LED1"))
    {
        state = STATE_UNLOCK;
        return;
    }
    state = STATE_VALIDATE;
}

void setup()
{
    pinMode(RELAY_PIN, OUTPUT);
    setRelay(false);

    Serial.begin(SERIAL_BAUD);
    delay(300);

    QRSerial.begin(GM65_BAUD, SERIAL_8N1, GM65_RX, GM65_TX);

    SPI.begin(TFT_SCLK, TFT_MISO, TFT_MOSI, TFT_CS);
    tft.init(240, 240, SPI_MODE3);
    tft.setRotation(0);
    tft.fillScreen(ST77XX_BLACK);

    showMessage("Safety Box", "Booting...");
    lastBatteryTick = millis();
    lastHeartbeatAt = millis();
    sendHeartbeat(simulatedBattery, currentLat, currentLng, "Available");

    state = STATE_LOCK;
}

void loop()
{
    updateSimBattery();
    tickHeartbeat();

    switch (state)
    {
    case STATE_INIT:
        state = STATE_WAIT_QR;
        break;
    case STATE_WAIT_QR:
    {
        if (millis() - lastCharTime > 2000)
        {
            showMessage("Scan QR", "Waiting...");
        }

        while (QRSerial.available())
        {
            char c = QRSerial.read();
            lastCharTime = millis();

            if (c == '\r' || c == '\n')
            {
                processBarcode();
                return;
            }
            else
            {
                barcodeBuffer += c;
            }
        }

        if (barcodeBuffer.length() > 0 && (millis() - lastCharTime > MSG_TIMEOUT_MS))
        {
            processBarcode();
        }
        break;
    }
    case STATE_VALIDATE:
    {
        showMessage("Validating", lastQr);
        bool ok = false;
        String message;
        bool success = sendScan(lastQr, ok, message);
        if (!success)
        {
            showMessage("Network Err", "Retry scan");
            delay(1500);
            state = STATE_WAIT_QR;
            break;
        }

        if (ok)
        {
            state = STATE_UNLOCK;
        }
        else
        {
            showMessage("DENY", message);
            delay(1500);
            state = STATE_WAIT_QR;
        }
        break;
    }
    case STATE_UNLOCK:
    {
        showMessage("UNLOCK", "Door open");
        setRelay(true);
        delay(30000);
        state = STATE_LOCK;
        break;
    }
    case STATE_LOCK:
    {
        showMessage("LOCK", "Door closed");
        setRelay(false);
        sendLockEvent();
        delay(1500);
        state = STATE_WAIT_QR;
        break;
    }
    }
}
