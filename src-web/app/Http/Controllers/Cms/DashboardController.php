<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\SafetyBoxDevice;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the CMS dashboard overview.
     */
    public function index(Request $request)
    {
        $orders = ServiceOrder::with(['qrCodes'])
            ->orderByDesc('updated_at')
            ->get();

        $devices = SafetyBoxDevice::orderBy('box_id')->get();

        $search = trim((string) $request->input('search', ''));
        $filterDate = (string) $request->input('filter_date', '');

        $logs = AccessLog::with(['device', 'qrCode.order'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->orWhere('box_id', 'like', "%{$search}%")
                        ->orWhere('log_type', 'like', "%{$search}%")
                        ->orWhereRaw("strftime('%Y-%m-%d', timestamp) like ?", ["%{$search}%"])
                        ->orWhereRaw("strftime('%d %m %Y %H:%M', timestamp) like ?", ["%{$search}%"])
                        ->orWhereHas('qrCode', function (Builder $qrQuery) use ($search) {
                            $qrQuery->where('order_id', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filterDate !== '', function (Builder $query) use ($filterDate) {
                $query->whereDate('timestamp', $filterDate);
            })
            ->orderByDesc('timestamp')
            ->paginate(10)
            ->withQueryString();

        return view('cms.dashboard', [
            'orders' => $orders,
            'devices' => $devices,
            'logs' => $logs,
            'logFilters' => [
                'search' => $search,
                'filter_date' => $filterDate,
            ],
        ]);
    }
}
