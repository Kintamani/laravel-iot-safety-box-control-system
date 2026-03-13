<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Store a new service order from the CMS form.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['required', 'string', 'max:255'],
            'phone_model' => ['nullable', 'string', 'max:255'],
        ]);

        ServiceOrder::create($data);

        return back()->with('status', 'Order baru berhasil dibuat.');
    }

    /**
     * Import orders from a CSV file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'File tidak bisa dibaca.']);
        }

        $headers = [];
        $created = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($headers)) {
                $headers = array_map(static fn ($header) => strtolower(trim($header)), $row);
                continue;
            }

            if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $payload = array_combine($headers, $row);
            if (!$payload) {
                continue;
            }

            $customerName = trim((string) ($payload['customer_name'] ?? ''));
            $customerContact = trim((string) ($payload['customer_contact'] ?? ''));

            if ($customerName === '' || $customerContact === '') {
                continue;
            }

            ServiceOrder::create([
                'spreadsheet_row_id' => $payload['spreadsheet_row_id'] ?? null,
                'customer_name' => $customerName,
                'customer_contact' => $customerContact,
                'phone_model' => $payload['phone_model'] ?? null,
                'status' => $payload['status'] ?? 'Pending',
            ]);

            $created++;
        }

        fclose($handle);

        return back()->with('status', "Import selesai. Total order baru: {$created}.");
    }

    /**
     * Generate QR code for the selected order and type.
     */
    public function generateQr(Request $request, ServiceOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:Pickup,Delivery'],
        ]);

        QRCode::where('order_id', $order->order_id)
            ->where('type', $data['type'])
            ->delete();

        $code = $this->makeQrCode($order, $data['type']);

        QRCode::create([
            'order_id' => $order->order_id,
            'qr_code' => $code,
            'type' => $data['type'],
        ]);

        return back()->with('status', "QR {$data['type']} berhasil dibuat.");
    }

    /**
     * Build a QR code payload string.
     */
    private function makeQrCode(ServiceOrder $order, string $type): string
    {
        $random = Str::upper(Str::random(8));
        return "SBX-{$order->order_id}-{$type}-{$random}";
    }
}
