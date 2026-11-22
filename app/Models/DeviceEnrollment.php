<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEnrollment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'managed_app_id',
        'enrollment_code',
        'label',
        'status',
        'max_uses',
        'uses_count',
        'expires_at',
        'enrolled_device_id',
        'created_by',
    ];

    protected $casts = [
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function managedApp(): BelongsTo
    {
        return $this->belongsTo(ManagedApp::class, 'managed_app_id');
    }

    public function enrolledDevice(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'enrolled_device_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->uses_count < $this->max_uses;
    }
}
