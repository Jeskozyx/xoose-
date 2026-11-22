<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppBuild extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_project_id',
        'app_revision_id',
        'status',
        'format',
        'engine_version',
        'started_at',
        'finished_at',
        'exit_code',
        'artifact_path',
        'log_path',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'exit_code' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(AppRevision::class, 'app_revision_id');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'cancelled']);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }
}
