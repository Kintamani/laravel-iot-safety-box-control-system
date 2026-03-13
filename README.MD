## IoT Safety Box Control System

Repo ini berisi dua bagian:
- `src-sketch` untuk firmware ESP32 (QR scan, relay/solenoid, LCD, battery, komunikasi ke CMS).
- `src-web` untuk CMS Laravel (order management, QR, monitoring, map).

### Struktur singkat
- `src-sketch/safety-box-control-system.ino`: firmware utama ESP32.
- `src-web/routes/web.php`: UI CMS & customer view.
- `src-web/routes/api.php`: endpoint perangkat IoT.

### Catatan
Detail flowchart dan ERD mengacu pada proposal "IOT Safety Box Control System".
