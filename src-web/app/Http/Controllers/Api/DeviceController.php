<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\QRCode;
use App\Models\SafetyBoxDevice;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeviceController extends Controller
{
    /**
     * Receive heartbeat payload from a device.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $this->assertDeviceKey($request);

        $data = $request->validate([
            'box_id' => ['required', 'string', 'max:255'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'status' => ['nullable', 'in:Available,In Use'],
        ]);

        $device = SafetyBoxDevice::updateOrCreate(
            ['box_id' => $data['box_id']],
            [
                'battery_level' => $data['battery_level'] ?? null,
                'gps_location' => $this->formatLocation($data['lat'] ?? null, $data['lng'] ?? null),
                'status' => $data['status'] ?? 'Available',
                'last_seen' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'box_id' => $device->box_id,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Validate a scanned QR code from a device.
     */
    public function scan(Request $request): JsonResponse
    {
        $this->assertDeviceKey($request);

        $data = $request->validate([
            'box_id' => ['required', 'string', 'max:255'],
            'qr_code' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $device = SafetyBoxDevice::firstOrCreate(
            ['box_id' => $data['box_id']],
            ['status' => 'Available']
        );

        $device->update([
            'gps_location' => $this->formatLocation($data['lat'] ?? null, $data['lng'] ?? null),
            'last_seen' => now(),
        ]);

        $qr = QRCode::with('order')->where('qr_code', $data['qr_code'])->first();
        if (!$qr) {
            return $this->deny('QR tidak dikenal.');
        }

        $alreadyUsed = AccessLog::where('scanned_qr_id', $qr->qr_id)
            ->where('log_type', 'Unlock')
            ->exists();

        if ($alreadyUsed) {
            return $this->deny('QR sudah digunakan.');
        }

        if (!$this->isQrAllowed($qr)) {
            return $this->deny('QR tidak sesuai status order.');
        }

        $this->applyOrderState($qr->order, $qr->type);

        $device->update([
            'status' => $qr->type === 'Pickup' ? 'In Use' : 'Available',
            'last_seen' => now(),
        ]);

        AccessLog::create([
            'box_id' => $device->box_id,
            'scanned_qr_id' => $qr->qr_id,
            'log_type' => 'Unlock',
            'timestamp' => Carbon::now(),
        ]);

        return response()->json([
            'ok' => true,
            'result' => 'valid',
            'action' => 'unlock',
            'order_id' => $qr->order_id,
            'qr_type' => $qr->type,
        ]);
    }

    /**
     * Record a lock event from a device.
     */
    public function lock(Request $request): JsonResponse
    {
        $this->assertDeviceKey($request);

        $data = $request->validate([
            'box_id' => ['required', 'string', 'max:255'],
            'qr_code' => ['nullable', 'string', 'max:255'],
        ]);

        $qr = null;
        if (!empty($data['qr_code'])) {
            $qr = QRCode::where('qr_code', $data['qr_code'])->first();
        }

        AccessLog::create([
            'box_id' => $data['box_id'],
            'scanned_qr_id' => $qr?->qr_id,
            'log_type' => 'Lock',
            'timestamp' => Carbon::now(),
        ]);

        SafetyBoxDevice::where('box_id', $data['box_id'])->update([
            'status' => 'Available',
            'last_seen' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * List all devices with their latest status and location.
     */
    public function devices(): JsonResponse
    {
        $devices = SafetyBoxDevice::orderBy('box_id')->get()->map(function (SafetyBoxDevice $device) {
            [$lat, $lng] = $this->parseLocation($device->gps_location);

            return [
                'box_id' => $device->box_id,
                'status' => $device->status,
                'battery_level' => $device->battery_level,
                'last_seen' => optional($device->last_seen)->toIso8601String(),
                'lat' => $lat,
                'lng' => $lng,
            ];
        });

        return response()->json([
            'ok' => true,
            'devices' => $devices,
        ]);
    }

    /**
     * Fetch order details and the latest device info.
     */
    public function order(ServiceOrder $order): JsonResponse
    {
        $order->load('qrCodes');

        $qrIds = $order->qrCodes->pluck('qr_id');
        $lastLog = AccessLog::with('device')
            ->whereIn('scanned_qr_id', $qrIds)
            ->orderByDesc('timestamp')
            ->first();

        $device = $lastLog?->device;
        [$lat, $lng] = $this->parseLocation($device?->gps_location);

        return response()->json([
            'ok' => true,
            'order' => [
                'order_id' => $order->order_id,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'phone_model' => $order->phone_model,
            ],
            'qr_codes' => $order->qrCodes->map(fn (QRCode $qr) => [
                'type' => $qr->type,
                'qr_code' => $qr->qr_code,
            ])->values(),
            'device' => $device ? [
                'box_id' => $device->box_id,
                'status' => $device->status,
                'battery_level' => $device->battery_level,
                'last_seen' => optional($device->last_seen)->toIso8601String(),
                'lat' => $lat,
                'lng' => $lng,
            ] : null,
        ]);
    }

    /**
     * Format latitude and longitude into a single string.
     */
    private function formatLocation(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return sprintf('%.6f,%.6f', $lat, $lng);
    }

    /**
     * Parse latitude and longitude from the stored location.
     */
    private function parseLocation(?string $location): array
    {
        if (!$location || !str_contains($location, ',')) {
            return [null, null];
        }

        [$lat, $lng] = array_map('trim', explode(',', $location, 2));
        return [is_numeric($lat) ? (float) $lat : null, is_numeric($lng) ? (float) $lng : null];
    }

    /**
     * Update order status based on QR type.
     */
    private function applyOrderState(ServiceOrder $order, string $type): void
    {
        if ($type === 'Pickup' && $order->status !== 'In Transit') {
            $order->update(['status' => 'In Transit']);
        }

        if ($type === 'Delivery' && $order->status !== 'Completed') {
            $order->update(['status' => 'Completed']);
        }
    }

    /**
     * Check if QR code is allowed based on order status.
     */
    private function isQrAllowed(QRCode $qr): bool
    {
        $status = $qr->order?->status;

        if ($qr->type === 'Pickup') {
            return $status === 'Pending';
        }

        if ($qr->type === 'Delivery') {
            return $status === 'In Transit';
        }

        return false;
    }

    /**
     * Build a deny response payload.
     */
    private function deny(string $reason): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'result' => 'deny',
            'action' => 'deny',
            'message' => $reason,
        ]);
    }

    /**
     * Enforce the device API key if configured.
     */
    private function assertDeviceKey(Request $request): void
    {
        $expected = config('services.device.key');
        if (!$expected) {
            return;
        }

        $provided = $request->header('X-Device-Key') ?? $request->input('device_key');
        if ($provided !== $expected) {
            abort(401, 'Invalid device key.');
        }
    }
}
