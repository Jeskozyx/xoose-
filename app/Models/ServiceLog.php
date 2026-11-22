<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'managed_app_id',
        'device_id',
        'actor_user_id',
        'service_key',
        'level',
        'event_type',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function managedApp(): BelongsTo
    {
        return $this->belongsTo(ManagedApp::class, 'managed_app_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'device_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
