<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_project_id',
        'revision_number',
        'features',
        'configuration',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'features' => 'array',
        'configuration' => 'array',
        'revision_number' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }
}
