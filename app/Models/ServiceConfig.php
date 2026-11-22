<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'managed_app_id',
        'device_id',
        'service_key',
        'config_version',
        'config_json',
        'is_active',
        'force_apply',
        'updated_by',
        'previous_version_id',
        'change_notes',
    ];

    protected $casts = [
        'config_version' => 'integer',
        'config_json' => 'array',
        'is_active' => 'boolean',
        'force_apply' => 'boolean',
    ];

    public function managedApp(): BelongsTo
    {
        return $this->belongsTo(ManagedApp::class, 'managed_app_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'device_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function toAgentPayload(): array
    {
        return [
            'service_key' => $this->service_key,
            'config_version' => $this->config_version,
            'config_json' => $this->config_json ?? [],
            'force_apply' => (bool) $this->force_apply,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
