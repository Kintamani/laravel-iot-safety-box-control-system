<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    protected $primaryKey = 'log_id';
    public $timestamps = false;

    protected $fillable = [
        'box_id',
        'scanned_qr_id',
        'log_type',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(SafetyBoxDevice::class, 'box_id', 'box_id');
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QRCode::class, 'scanned_qr_id', 'qr_id');
    }
}
