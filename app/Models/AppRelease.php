<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'managed_app_id',
        'version',
        'artifact_path',
        'artifact_url',
        'sha256_checksum',
        'signature',
        'release_notes',
        'is_active',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function managedApp(): BelongsTo
    {
        return $this->belongsTo(ManagedApp::class, 'managed_app_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function toAgentPayload(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'artifact_path' => $this->artifact_path,
            'artifact_url' => $this->artifact_url ?: "/api/v1/agent/releases/{$this->id}/download",
            'sha256_checksum' => $this->sha256_checksum,
            'signature' => $this->signature ?? '',
            'release_notes' => $this->release_notes ?? '',
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
