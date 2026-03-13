<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\ServiceOrder;

class CustomerController extends Controller
{
    /**
     * Display customer tracking page for an order.
     */
    public function show(ServiceOrder $order)
    {
        $order->load('qrCodes');

        $qrIds = $order->qrCodes->pluck('qr_id');
        $lastLog = AccessLog::with('device')
            ->whereIn('scanned_qr_id', $qrIds)
            ->orderByDesc('timestamp')
            ->first();

        $device = $lastLog?->device;

        return view('customer.show', [
            'order' => $order,
            'device' => $device,
        ]);
    }
}
