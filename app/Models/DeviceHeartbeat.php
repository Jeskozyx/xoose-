<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'app_version',
        'agent_uptime',
        'ip_address',
        'services_summary',
        'received_at',
    ];

    protected $casts = [
        'agent_uptime' => 'float',
        'services_summary' => 'array',
        'received_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'device_id');
    }
}
