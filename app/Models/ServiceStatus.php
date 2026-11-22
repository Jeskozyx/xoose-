<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceStatus extends Model
{
    use HasFactory;

    protected $table = 'service_status';

    protected $fillable = [
        'device_id',
        'service_key',
        'current_status',
        'active_config_version',
        'details',
        'reported_at',
    ];

    protected $casts = [
        'active_config_version' => 'integer',
        'details' => 'array',
        'reported_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'device_id');
    }
}
