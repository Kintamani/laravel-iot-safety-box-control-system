<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafetyBoxDevice extends Model
{
    protected $primaryKey = 'box_id';
    protected $table = 'safety_box_devices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'box_id',
        'status',
        'battery_level',
        'gps_location',
        'last_seen',
    ];

    protected $casts = [
        'battery_level' => 'integer',
        'last_seen' => 'datetime',
    ];

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'box_id', 'box_id');
    }
}
