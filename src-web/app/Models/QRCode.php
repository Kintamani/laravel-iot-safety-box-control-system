<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QRCode extends Model
{
    protected $primaryKey = 'qr_id';
    protected $table = 'qr_codes';

    protected $fillable = [
        'order_id',
        'qr_code',
        'type',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'order_id', 'order_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'scanned_qr_id', 'qr_id');
    }
}
