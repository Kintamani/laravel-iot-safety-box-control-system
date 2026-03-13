@extends('layouts.app')

@section('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endsection

@section('content')
    @php
        $pickup = $order->qrCodes->firstWhere('type', 'Pickup');
        $delivery = $order->qrCodes->firstWhere('type', 'Delivery');
        $coords = $device?->gps_location ? explode(',', $device->gps_location) : [null, null];
        $lat = isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null;
        $lng = isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null;
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Customer View</p>
            <h2 class="text-2xl font-semibold text-slate-900">Order #{{ $order->order_id }}</h2>
            <p class="mt-1 text-sm text-slate-500">Status: <span id="order-status"
                    class="font-semibold text-emerald-700">{{ $order->status }}</span></p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">QR Pickup</p>
                    @if ($pickup)
                        <img class="mt-3 w-full rounded-xl border border-slate-200 bg-white p-3"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($pickup->qr_code) }}"
                            alt="QR Pickup" />
                        <p class="mt-2 text-xs text-slate-500">{{ $pickup->qr_code }}</p>
                    @else
                        <p class="mt-3 text-sm text-slate-500">QR belum tersedia.</p>
                    @endif
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">QR Delivery</p>
                    @if ($delivery)
                        <img class="mt-3 w-full rounded-xl border border-slate-200 bg-white p-3"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($delivery->qr_code) }}"
                            alt="QR Delivery" />
                        <p class="mt-2 text-xs text-slate-500">{{ $delivery->qr_code }}</p>
                    @else
                        <p class="mt-3 text-sm text-slate-500">QR belum tersedia.</p>
                    @endif
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Updates</p>
                <div class="mt-3 space-y-2 text-sm text-slate-600">
                    <div class="flex items-center gap-2">
                        <span id="step-pending" class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Menunggu penjemputan
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="step-transit" class="h-2 w-2 rounded-full bg-slate-300"></span>
                        Sedang dikirim ke service center
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="step-complete" class="h-2 w-2 rounded-full bg-slate-300"></span>
                        Selesai / dikembalikan
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Tracking</p>
            <h2 class="text-xl font-semibold text-slate-900">Lokasi Safety Box</h2>
            <div id="customer-map" class="mt-4 h-72 w-full rounded-2xl border border-slate-200"></div>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <p>Box: <span id="device-id" class="font-semibold text-slate-900">{{ $device?->box_id ?? 'N/A' }}</span></p>
                <p>Battery: <span id="device-battery"
                        class="font-semibold text-slate-900">{{ $device?->battery_level ?? '--' }}%</span></p>
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
            const pending = document.getElementById('step-pending');
            const transit = document.getElementById('step-transit');
            const complete = document.getElementById('step-complete');
            pending.className = 'h-2 w-2 rounded-full bg-emerald-500';
            transit.className = 'h-2 w-2 rounded-full bg-slate-300';
            complete.className = 'h-2 w-2 rounded-full bg-slate-300';

            if (status === 'In Transit') {
                transit.className = 'h-2 w-2 rounded-full bg-emerald-500';
            }
            if (status === 'Completed') {
                transit.className = 'h-2 w-2 rounded-full bg-emerald-500';
                complete.className = 'h-2 w-2 rounded-full bg-emerald-500';
            }
        }

        async function refreshOrder() {
            try {
                const response = await fetch('/api/orders/{{ $order->order_id }}');
                const data = await response.json();
                if (!data.ok) return;

                document.getElementById('order-status').textContent = data.order.status;
                updateSteps(data.order.status);

                if (data.device) {
                    document.getElementById('device-id').textContent = data.device.box_id;
                    document.getElementById('device-battery').textContent = `${data.device.battery_level ?? '--'}%`;
                    document.getElementById('device-lastseen').textContent = data.device.last_seen ?? 'N/A';
                    setMarker(data.device.lat, data.device.lng);
                } else {
                    setMarker(null, null);
                }
            } catch (error) {
                console.error(error);
            }
        }

        updateSteps('{{ $order->status }}');
        setMarker(@json($lat), @json($lng));

        setInterval(refreshOrder, 8000);
    </script>
@endsection
