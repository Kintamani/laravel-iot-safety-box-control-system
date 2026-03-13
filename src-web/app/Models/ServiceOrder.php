<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceOrder extends Model
{
    protected $primaryKey = 'order_id';

    protected $fillable = [
        'spreadsheet_row_id',
        'customer_name',
        'customer_contact',
        'phone_model',
        'status',
    ];

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QRCode::class, 'order_id', 'order_id');
    }

    public function pickupQr(): HasOne
    {
        return $this->hasOne(QRCode::class, 'order_id', 'order_id')
            ->where('type', 'Pickup')
            ->latest('qr_id');
    }

    public function deliveryQr(): HasOne
    {
        return $this->hasOne(QRCode::class, 'order_id', 'order_id')
            ->where('type', 'Delivery')
            ->latest('qr_id');
    }
}
