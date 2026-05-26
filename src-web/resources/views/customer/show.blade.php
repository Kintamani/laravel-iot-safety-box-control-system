@extends('layouts.app')

@section('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endsection

@section('content')
    @php
        $qrCards = [
            'pickup-open' => ['title' => 'Pickup Open', 'hint' => 'Scan untuk membuka box saat penjemputan.'],
            'pickup-closed' => ['title' => 'Pickup Closed', 'hint' => 'Scan setelah box ditutup untuk memulai In Transit.'],
            'delivery-open' => ['title' => 'Delivery Open', 'hint' => 'Scan untuk membuka box saat pengembalian.'],
            'delivery-closed' => ['title' => 'Delivery Closed', 'hint' => 'Scan setelah box ditutup untuk menyelesaikan order.'],
        ];
        $timeline = [
            'pickup-open' => 'Buka box untuk pickup',
            'pickup-closed' => 'Konfirmasi pickup selesai',
            'delivery-open' => 'Buka box untuk delivery',
            'delivery-closed' => 'Konfirmasi delivery selesai',
        ];
        $coords = $device?->gps_location ? explode(',', $device->gps_location) : [null, null];
        $lat = isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null;
        $lng = isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null;
        $qrStatus = [];

        foreach ($timeline as $qrType => $label) {
            $qr = $order->qrCodes->firstWhere('type', $qrType);
            $logType = str_ends_with($qrType, '-open') ? 'Unlock' : 'Lock';
            $qrStatus[$qrType] =
                $qr &&
                $qr->accessLogs->contains(fn($log) => $log->log_type === $logType);
        }
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Customer View</p>
            <h2 class="text-2xl font-semibold text-slate-900">Order #{{ $order->order_id }}</h2>
            <p class="mt-1 text-sm text-slate-500">Status: <span id="order-status"
                    class="font-semibold text-emerald-700">{{ $order->status }}</span></p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ($qrCards as $qrType => $qrLabel)
                    @php
                        $qr = $order->qrCodes->firstWhere('type', $qrType);
                        $isDone = $qrStatus[$qrType] ?? false;
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    {{ $qrLabel['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $qrLabel['hint'] }}</p>
                            </div>
                            <span
                                id="badge-{{ $qrType }}"
                                class="rounded-full px-3 py-1 text-[11px] font-semibold {{ $isDone ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $isDone ? 'Selesai' : 'Menunggu' }}
                            </span>
                        </div>
                        @if ($qr)
                            <img class="mt-3 w-full rounded-xl border border-slate-200 bg-white p-3"
                                src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($qr->qr_code) }}"
                                alt="{{ $qrLabel['title'] }}" />
                            <p class="mt-2 text-xs text-slate-500">{{ $qr->qr_code }}</p>
                        @else
                            <p class="mt-3 text-sm text-slate-500">QR belum tersedia.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">4-Step Progress</p>
                <div class="mt-3 space-y-2 text-sm text-slate-600">
                    @foreach ($timeline as $qrType => $label)
                        <div class="flex items-center gap-2">
                            <span id="step-{{ $qrType }}"
                                class="h-2 w-2 rounded-full {{ ($qrStatus[$qrType] ?? false) ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Tracking</p>
            <h2 class="text-xl font-semibold text-slate-900">Lokasi Safety Box</h2>
            <div id="customer-map" class="mt-4 h-72 w-full rounded-2xl border border-slate-200"></div>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <p>Box: <span id="device-id" class="font-semibold text-slate-900">{{ $device?->box_id ?? 'N/A' }}</span></p>
                <p>Battery Doorlock: <span id="device-battery-doorlock"
                        class="font-semibold text-slate-900">{{ $device?->battery_doorlock ?? '--' }}%</span></p>
                <p>Battery Device: <span id="device-battery-device"
                        class="font-semibold text-slate-900">{{ $device?->battery_device ?? '--' }}%</span></p>
                <p>Last seen: <span id="device-lastseen"
                        class="font-semibold text-slate-900">{{ $device?->last_seen?->diffForHumans() ?? 'N/A' }}</span></p>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const map = L.map('customer-map');
        L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {
            attribution: 'Google Maps',
            maxZoom: 18,
        }).addTo(map);

        let marker = null;

        function setMarker(lat, lng) {
            if (!lat || !lng) {
                map.setView([-6.9, 107.6], 11);
                return;
            }
            if (!marker) {
                marker = L.marker([lat, lng]).addTo(map);
                map.setView([lat, lng], 13);
            } else {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng], { animate: true });
            }
        }

        function updateSteps(status) {
            const steps = {
                'pickup-open': document.getElementById('step-pickup-open'),
                'pickup-closed': document.getElementById('step-pickup-closed'),
                'delivery-open': document.getElementById('step-delivery-open'),
                'delivery-closed': document.getElementById('step-delivery-closed'),
            };

            Object.values(steps).forEach(step => {
                step.className = 'h-2 w-2 rounded-full bg-slate-300';
            });

            if (!Array.isArray(status)) {
                return;
            }

            status.forEach(type => {
                if (steps[type]) {
                    steps[type].className = 'h-2 w-2 rounded-full bg-emerald-500';
                }
            });
        }

        function updateQrBadges(qrCodes) {
            qrCodes.forEach(qr => {
                const badge = document.getElementById(`badge-${qr.type}`);
                if (!badge) {
                    return;
                }

                badge.textContent = qr.done ? 'Selesai' : 'Menunggu';
                badge.className = qr.done ?
                    'rounded-full px-3 py-1 text-[11px] font-semibold bg-emerald-100 text-emerald-700' :
                    'rounded-full px-3 py-1 text-[11px] font-semibold bg-slate-200 text-slate-600';
            });
        }

        async function refreshOrder() {
            try {
                const response = await fetch('/api/orders/{{ $order->order_id }}');
                const data = await response.json();
                if (!data.ok) return;

                document.getElementById('order-status').textContent = data.order.status;
                updateSteps(data.qr_codes.filter(qr => qr.done).map(qr => qr.type));
                updateQrBadges(data.qr_codes);

                if (data.device) {
                    document.getElementById('device-id').textContent = data.device.box_id;
                    document.getElementById('device-battery-doorlock').textContent = `${data.device.battery_doorlock ?? '--'}%`;
                    document.getElementById('device-battery-device').textContent = `${data.device.battery_device ?? '--'}%`;
                    document.getElementById('device-lastseen').textContent = data.device.last_seen ?? 'N/A';
                    setMarker(data.device.lat, data.device.lng);
                } else {
                    setMarker(null, null);
                }
            } catch (error) {
                console.error(error);
            }
        }

        updateSteps(@json(array_keys(array_filter($qrStatus))));
        setMarker(@json($lat), @json($lng));

        setInterval(refreshOrder, 8000);
    </script>
@endsection
