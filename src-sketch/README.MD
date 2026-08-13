## Safety Box ESP32 Sketch

Ringkasan alur (sesuai flowchart):

- Boot → init hardware
- Heartbeat → kirim status device + GPS ke CMS
- Tunggu scan QR `pickup-open` / `delivery-open` → validasi
- Jika valid: unlock relay → tunggu pintu dibuka lalu ditutup
- Setelah box tertutup: scan QR `pickup-closed` / `delivery-closed`
- CMS finalisasi order ke `In Transit` atau `Completed`

### Konfigurasi cepat

Edit variabel di `modules/modules.ino`:

- `WIFI_SSID`, `WIFI_PASS`
- `CMS_BASE_URL` (contoh: `http://192.168.1.100:8000`)
- `DEVICE_API_KEY` (opsional, harus sama dengan `.env` di CMS)
- `BOX_ID`

### Endpoint Laravel CMS

Sketch utama memakai endpoint berikut:

- `POST /api/device/heartbeat`
- `POST /api/device/scan`

Base URL sketch sekarang disiapkan untuk HTTP biasa, misalnya:

- `http://domain-anda/...`

Payload heartbeat mengikuti controller Laravel:

- `box_id`
- `battery_doorlock`
- `battery_device`
- `status` (`Available` / `In Use`)
- `door_status` (`Open` / `Closed`) dari sensor LDR
- `lat`, `lng` bila GPS valid

### Pin utama

- GM65 Barcode: `RX = GPIO22`, `TX = GPIO21`
- GPS: `RX = GPIO16`, `TX = GPIO17`
- Relay: `RELAY_PIN = GPIO25`
- Voltage Sensor 1: `GPIO34`
- Voltage Sensor 2: `GPIO35`
- LDR: `GPIO32`

### TFT_eSPI

Sketch display sekarang memakai library `TFT_eSPI`.
Pin LCD harus disesuaikan di konfigurasi library `TFT_eSPI`:

- `TFT_SCLK = GPIO18`
- `TFT_MOSI = GPIO23`
- `TFT_DC = GPIO27`
- `TFT_RST = GPIO26`
- `TFT_CS = -1` atau CS di-ground

Wiring final yang tidak bentrok:
📟 LCD ST7789 (AMAN)
SCL → GPIO18
SDA → GPIO23
RES → GPIO26
DC → GPIO27
CS → GND
BLK → 3.3V

📦 GM65 Barcode
TX → GPIO22
RX → GPIO21
VCC → 5V
GND → GND

⚡ Relay
IN → GPIO25
VCC → 5V
GND → GND

📡 GPS
TX → GPIO17
RX → GPIO16

🔌 Voltage Sensor
Sensor 1 → GPIO34 12V
Sensor 2 → GPIO35 5V
Sensor + → VCC (optional, tidak wajib)
Sensor - → GND

🌞 LDR (LM393 / HW-103) x
AO → GPIO32
VCC → 3.3V
GND → GND
