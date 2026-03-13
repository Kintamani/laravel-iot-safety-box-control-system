@extends('layouts.app')

@section('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endsection

@section('content')
    @php
        $devicePayload = $devices->map(function ($device) {
            $coords = $device->gps_location ? explode(',', $device->gps_location) : [null, null];
            return [
                'box_id' => $device->box_id,
                'status' => $device->status,
                'battery_level' => $device->battery_level,
                'lat' => isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null,
                'lng' => isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null,
            ];
        });
    @endphp
    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Lokasi GPS</p>
            <h2 class="text-xl font-semibold text-slate-900">Live Map</h2>
            <div id="device-map" class="mt-4 h-100 w-full rounded-2xl border border-slate-200"></div>
            <p class="mt-3 text-xs text-slate-500">Update otomatis setiap 8 detik.</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Device Monitoring</p>
            <h2 class="text-xl font-semibold text-slate-900">Safety Box Devices</h2>
            <div class="mt-4 max-h-96 space-y-3 overflow-y-auto pr-1">
                @forelse ($devices as $device)
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-900">{{ $device->box_id }}</p>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $device->status }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                            <span>Battery: {{ $device->battery_level ?? '--' }}%</span>
                            <span>Last seen: {{ $device->last_seen?->diffForHumans() ?? 'N/A' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
                        Belum ada device terdaftar.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-8 grid gap-6">
        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Order Management</p>
                    <h2 class="text-xl font-semibold text-slate-900">Daftar Order & QR</h2>
                </div>
                {{-- @if ($orders->isNotEmpty())
                    <a href="{{ route('orders.show', $orders->first()) }}"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white">
                        Preview Customer View
                    </a>
                @else
                    <span class="text-xs text-slate-400">Buat order terlebih dulu</span>
                @endif --}}
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_2.6fr]">
                <div class="space-y-4">
                    <form method="POST" action="{{ route('cms.orders.store') }}"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        @csrf
                        <p class="text-sm font-semibold text-slate-900">Tambah Order Manual</p>
                        <div class="mt-3 grid gap-3 text-sm">
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Nama</label>
                            <input name="customer_name" placeholder="Nama customer"
                                class="-mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Kontak</label>
                            <input name="customer_contact" placeholder="Kontak (WA/Email)"
                                class="-mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Model</label>
                            <input name="phone_model" placeholder="Model handphone"
                                class="-mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
                            <button
                                class="mt-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white">
                                Simpan Order
                            </button>
                        </div>
                    </form>

                    {{-- <form method="POST" action="{{ route('cms.orders.import') }}" enctype="multipart/form-data"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        @csrf
                        <p class="text-sm font-semibold text-slate-900">Import dari Spreadsheet (CSV)</p>
                        <div class="mt-3 grid gap-3 text-sm">
                            <input type="file" name="file"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2" />
                            <p class="text-xs text-slate-500">Header wajib: customer_name, customer_contact, phone_model, status
                            </p>
                            <button
                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white">
                                Import CSV
                            </button>
                        </div>
                    </form> --}}
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="max-h-[520px] overflow-y-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="sticky top-0 bg-slate-100 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Order</th>
                                    <th class="px-4 py-3">Customer</th>
                                    <th class="px-4 py-3">Phone</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">QR</th>
                                    <th class="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($orders as $order)
                                    @php
                                        $pickup = $order->qrCodes->firstWhere('type', 'Pickup');
                                        $delivery = $order->qrCodes->firstWhere('type', 'Delivery');
                                    @endphp
                                    <tr class="bg-white/60">
                                        <td class="px-4 py-3 font-medium text-slate-900">#{{ $order->order_id }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-slate-900">{{ $order->customer_name }}</p>
                                            <p class="text-xs text-slate-500">{{ $order->customer_contact }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $order->phone_model ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-500">
                                            <p>Pickup: {{ $pickup?->qr_code ? 'Ada' : 'Belum' }}</p>
                                            <p>Delivery: {{ $delivery?->qr_code ? 'Ada' : 'Belum' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <form method="POST" action="{{ route('cms.orders.qr', $order) }}">
                                                    @csrf
                                                    <input type="hidden" name="type" value="Pickup" />
                                                    <button
                                                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:border-slate-300">
                                                        Generate Pickup
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('cms.orders.qr', $order) }}">
                                                    @csrf
                                                    <input type="hidden" name="type" value="Delivery" />
                                                    <button
                                                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:border-slate-300">
                                                        Generate Delivery
                                                    </button>
                                                </form>
                                                <a href="{{ route('orders.show', $order) }}"
                                                    class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                                                    Customer View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada
                                            order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <section class="mt-8 rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Activity Log</p>
                <h2 class="text-xl font-semibold text-slate-900">Riwayat Unlock/Lock</h2>
            </div>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase tracking-[0.15em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Box</th>
                        <th class="px-4 py-3">Log</th>
                        <th class="px-4 py-3">Order</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($logs as $log)
                        <tr class="bg-white/60">
                            <td class="px-4 py-3 text-slate-600">{{ $log->timestamp?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $log->box_id }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $log->log_type }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $log->qrCode?->order_id ? '#' . $log->qrCode->order_id : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada aktivitas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const initialDevices = @json($devicePayload);

        const map = L.map('device-map');
        L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {
            attribution: 'Google Maps',
            maxZoom: 20
        }).addTo(map);

        const markers = new Map();

        function setViewFromDevices(devices, keepZoom = false) {
            const first = devices.find(device => device.lat && device.lng);
            if (first) {
                const zoom = keepZoom ? map.getZoom() : 15;
                map.setView([first.lat, first.lng], zoom, { animate: true });
            } else {
                map.setView([-6.9, 107.6], keepZoom ? map.getZoom() : 15);
            }
        }

        function updateMarkers(devices) {
            devices.forEach(device => {
                if (!device.lat || !device.lng) {
                    return;
                }
                const label = `${device.box_id} • ${device.status} • ${device.battery_level ?? '--'}%`;
                if (markers.has(device.box_id)) {
                    markers.get(device.box_id).setLatLng([device.lat, device.lng]).bindPopup(label);
                } else {
                    const marker = L.marker([device.lat, device.lng]).addTo(map).bindPopup(label);
                    markers.set(device.box_id, marker);
                }
            });
        }

        setViewFromDevices(initialDevices);
        updateMarkers(initialDevices);

        async function refreshDevices() {
            try {
                const response = await fetch('/api/devices');
                const data = await response.json();
                if (!data.ok) return;
                updateMarkers(data.devices);
                setViewFromDevices(data.devices, true);
            } catch (error) {
                console.error(error);
            }
        }

        setInterval(refreshDevices, 8000);
    </script>
@endsection
