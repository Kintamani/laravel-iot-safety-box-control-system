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
        'door_status',
        'relay_status',
        'relay_command_pending',
        'relay_expires_at',
        'battery_doorlock',
        'battery_device',
        'gps_location',
        'last_seen',
    ];

    protected $casts = [
        'door_status' => 'string',
        'relay_command_pending' => 'boolean',
        'relay_expires_at' => 'datetime',
        'battery_doorlock' => 'integer',
        'battery_device' => 'integer',
        'last_seen' => 'datetime',
    ];

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'box_id', 'box_id');
    }
}
