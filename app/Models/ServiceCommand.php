<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCommand extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const TERMINAL_STATUSES = [
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'command_id',
        'device_id',
        'service_key',
        'action',
        'payload',
        'status',
        'issued_by',
        'expires_at',
        'delivered_at',
        'acknowledged_at',
        'completed_at',
        'result_message',
        'result_payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'result_payload' => 'array',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'command_id';
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'device_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Format payload standar sesuai spesifikasi Blueprint & LoveYou Agent CommandExecutor.
     */
    public function toAgentPayload(): array
    {
        return [
            'command_id' => $this->command_id,
            'device_id' => $this->device?->device_uuid ?? (string) $this->device_id,
            'service' => $this->service_key,
            'service_key' => $this->service_key,
            'action' => $this->action,
            'payload' => $this->payload ?? [],
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
