## Safety Box ESP32 Sketch

Ringkasan alur (sesuai flowchart):
- Boot → init hardware → cek battery
- Jika battery rendah: report + deep sleep
- Jika OK: tunggu scan QR → kirim QR + GPS ke CMS → validasi
- Jika valid: unlock solenoid → lock → log → deep sleep

### Konfigurasi cepat
Edit variabel di `safety-box-control-system.ino`:
- `WIFI_SSID`, `WIFI_PASS`
- `CMS_BASE_URL` (contoh: `http://192.168.1.100:8000`)
- `DEVICE_API_KEY` (opsional, harus sama dengan `.env` di CMS)
- `BOX_ID`

### Pin utama
- GM65: `GM65_RX = 35`, `GM65_TX = 32`
- Relay: `RELAY_PIN = 12`
- Battery ADC: `BATTERY_ADC_PIN = 34`

Sesuaikan pin sesuai wiring final.
