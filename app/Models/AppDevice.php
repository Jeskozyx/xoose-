<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppDevice extends Model
{
    use HasFactory;

    public const STATUS_ONLINE = 'Online';
    public const STATUS_OFFLINE = 'Offline';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_REVOKED = 'Revoked';

    protected $fillable = [
        'device_uuid',
        'managed_app_id',
        'device_code',
        'hostname',
        'os_info',
        'app_version',
        'status',
        'token_hash',
        'last_ip',
        'revoked_at',
        'last_seen_at',
        'metadata',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function managedApp(): BelongsTo
    {
        return $this->belongsTo(ManagedApp::class, 'managed_app_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(ServiceCommand::class, 'device_id')->orderByDesc('id');
    }

    public function configs(): HasMany
    {
        return $this->hasMany(ServiceConfig::class, 'device_id');
    }

    public function serviceStatuses(): HasMany
    {
        return $this->hasMany(ServiceStatus::class, 'device_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class, 'device_id')->orderByDesc('received_at');
    }

    public function latestHeartbeat(): HasOne
    {
        return $this->hasOne(DeviceHeartbeat::class, 'device_id')->latestOfMany('received_at');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class, 'device_id')->orderByDesc('id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->status === self::STATUS_REVOKED;
    }

    public function isOnline(): bool
    {
        if ($this->isRevoked() || $this->last_seen_at === null) {
            return false;
        }

        $timeout = (int) config('remote-control.heartbeat_timeout_seconds', 90);

        return $this->status === self::STATUS_ONLINE
            && $this->last_seen_at->greaterThanOrEqualTo(now()->subSeconds($timeout));
    }
}
