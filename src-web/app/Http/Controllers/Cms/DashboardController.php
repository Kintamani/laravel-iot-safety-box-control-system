<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\SafetyBoxDevice;
use App\Models\ServiceOrder;

class DashboardController extends Controller
{
    /**
     * Display the CMS dashboard overview.
     */
    public function index()
    {
        $orders = ServiceOrder::with(['qrCodes'])
            ->orderByDesc('updated_at')
            ->get();

        $devices = SafetyBoxDevice::orderBy('box_id')->get();

        $logs = AccessLog::with(['device', 'qrCode.order'])
            ->orderByDesc('timestamp')
            ->limit(50)
            ->get();

        return view('cms.dashboard', [
            'orders' => $orders,
            'devices' => $devices,
            'logs' => $logs,
        ]);
    }
}
