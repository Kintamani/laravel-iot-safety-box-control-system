<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\ServiceOrder;
use Illuminate\Support\Carbon;

class CustomerController extends Controller
{
    /**
     * Display customer tracking page for an order.
     */
    public function show(ServiceOrder $order)
    {
        $order->load('qrCodes.accessLogs');

        $qrIds = $order->qrCodes->pluck('qr_id');
        $lastLog = AccessLog::with('device')
            ->whereIn('scanned_qr_id', $qrIds)
            ->orderByDesc('timestamp')
            ->first();

        $device = $lastLog?->device;
        $deviceLastSeen = $device?->last_seen
            ? Carbon::parse($device->last_seen)
                ->timezone(config('app.timezone'))
                ->translatedFormat('d M Y H:i') . ' WIB'
            : 'N/A';

        return view('customer.show', [
            'order' => $order,
            'device' => $device,
            'deviceLastSeen' => $deviceLastSeen,
        ]);
    }
}
