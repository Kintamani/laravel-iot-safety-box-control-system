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
            'battery_doorlock' => ['nullable', 'integer', 'min:0', 'max:100'],
            'battery_device' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'status' => ['nullable', 'in:Available,In Use'],
            'door_status' => ['nullable', 'in:Open,Closed'],
        ]);

        $device = SafetyBoxDevice::updateOrCreate(
            ['box_id' => $data['box_id']],
            [
                'battery_doorlock' => $data['battery_doorlock'] ?? null,
                'battery_device' => $data['battery_device'] ?? null,
                'gps_location' => $this->formatLocation($data['lat'] ?? null, $data['lng'] ?? null),
                'status' => $data['status'] ?? 'Available',
                'door_status' => $data['door_status'] ?? null,
                'last_seen' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'box_id' => $device->box_id,
            'server_time' => $this->isoTimestamp(now()),
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

        $action = $this->qrAction($qr->type);
        if (!$action) {
            return $this->deny('Tipe QR tidak didukung.');
        }

        if ($this->hasScanBeenUsed($qr, $action)) {
            return $this->deny('QR sudah digunakan.');
        }

        if (!$this->isQrAllowed($qr)) {
            return $this->deny('QR tidak sesuai status order.');
        }

        $this->applyOrderState($qr->order, $qr->type);

        $device->update([
            'status' => $action === 'unlock' ? 'In Use' : 'Available',
            'last_seen' => now(),
        ]);

        AccessLog::create([
            'box_id' => $device->box_id,
            'scanned_qr_id' => $qr->qr_id,
            'log_type' => $action === 'unlock' ? 'Unlock' : 'Lock',
            'timestamp' => Carbon::now(),
        ]);

        return response()->json([
            'ok' => true,
            'result' => 'valid',
            'action' => $action,
            'order_id' => $qr->order_id,
            'qr_type' => $qr->type,
            'next_qr_type' => $this->nextQrType($qr->type),
            'message' => $this->successMessage($qr->type),
        ]);
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
                'door_status' => $device->door_status,
                'battery_doorlock' => $device->battery_doorlock,
                'battery_device' => $device->battery_device,
                'last_seen' => $device->last_seen?->format('Y-m-d H:i:s'),
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
            'qr_codes' => $order->qrCodes->map(fn(QRCode $qr) => [
                'type' => $qr->type,
                'qr_code' => $qr->qr_code,
                'done' => $this->hasQrLog($qr, $this->isOpenQr($qr->type) ? 'Unlock' : 'Lock'),
            ])->values(),
            'device' => $device ? [
                'box_id' => $device->box_id,
                'status' => $device->status,
                'door_status' => $device->door_status,
                'battery_doorlock' => $device->battery_doorlock,
                'battery_device' => $device->battery_device,
                'last_seen' => $device->last_seen?->format('Y-m-d H:i:s'),
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
        if ($type === 'pickup-closed' && $order->status !== 'In Transit') {
            $order->update(['status' => 'In Transit']);
        }

        if ($type === 'delivery-closed' && $order->status !== 'Completed') {
            $order->update(['status' => 'Completed']);
        }
    }

    /**
     * Check if QR code is allowed based on order status.
     */
    private function isQrAllowed(QRCode $qr): bool
    {
        $status = $qr->order?->status;
        return match ($qr->type) {
            'pickup-open' => $status === 'Pending',
            'pickup-closed' => $status === 'Pending'
                && $this->hasQrLog($this->pairedOpenQr($qr), 'Unlock'),
            'delivery-open' => $status === 'In Transit',
            'delivery-closed' => $status === 'In Transit'
                && $this->hasQrLog($this->pairedOpenQr($qr), 'Unlock'),
            default => false,
        };
    }

    /**
     * Determine whether the QR triggers an unlock or a completion event.
     */
    private function qrAction(string $type): ?string
    {
        if ($this->isOpenQr($type)) {
            return 'unlock';
        }

        if ($this->isClosedQr($type)) {
            return 'complete';
        }

        return null;
    }

    /**
     * Return the next QR type expected by the workflow.
     */
    private function nextQrType(string $type): ?string
    {
        return match ($type) {
            'pickup-open' => 'pickup-closed',
            'delivery-open' => 'delivery-closed',
            default => null,
        };
    }

    /**
     * Build a success message for the current QR.
     */
    private function successMessage(string $type): string
    {
        return match ($type) {
            'pickup-open' => 'Box terbuka. Tutup box lalu scan pickup-closed.',
            'pickup-closed' => 'Pickup selesai. Order masuk In Transit.',
            'delivery-open' => 'Box terbuka. Tutup box lalu scan delivery-closed.',
            'delivery-closed' => 'Delivery selesai. Order Completed.',
            default => 'QR valid.',
        };
    }

    /**
     * Check whether a QR has already been consumed for its intended action.
     */
    private function hasScanBeenUsed(QRCode $qr, string $action): bool
    {
        $logType = $action === 'unlock' ? 'Unlock' : 'Lock';
        return $this->hasQrLog($qr, $logType);
    }

    /**
     * Check whether a QR has a specific access log recorded.
     */
    private function hasQrLog(?QRCode $qr, string $logType): bool
    {
        if (!$qr) {
            return false;
        }

        return AccessLog::where('scanned_qr_id', $qr->qr_id)
            ->where('log_type', $logType)
            ->exists();
    }

    /**
     * Find the matching open QR for a closed QR of the same order.
     */
    private function pairedOpenQr(QRCode $qr): ?QRCode
    {
        $openType = match ($qr->type) {
            'pickup-closed' => 'pickup-open',
            'delivery-closed' => 'delivery-open',
            default => null,
        };

        if (!$openType) {
            return null;
        }

        return QRCode::where('order_id', $qr->order_id)
            ->where('type', $openType)
            ->latest('qr_id')
            ->first();
    }

    /**
     * Determine whether a QR type is an "open" step.
     */
    private function isOpenQr(string $type): bool
    {
        return str_ends_with($type, '-open');
    }

    /**
     * Determine whether a QR type is a "closed" step.
     */
    private function isClosedQr(string $type): bool
    {
        return str_ends_with($type, '-closed');
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

    /**
     * Format timestamp as ISO-8601.
     */
    private function isoTimestamp($timestamp): string
    {
        return $timestamp->toIso8601String();
    }
}
