<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_template_id',
        'name',
        'slug',
        'status',
        'features',
        'configuration',
        'workspace_path',
        'created_by',
    ];

    protected $casts = [
        'features' => 'array',
        'configuration' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AppTemplate::class, 'app_template_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AppRevision::class)->orderByDesc('revision_number');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(AppRevision::class)->latestOfMany('revision_number');
    }

    public function builds(): HasMany
    {
        return $this->hasMany(AppBuild::class)->orderByDesc('id');
    }

    public function latestBuild(): HasOne
    {
        return $this->hasOne(AppBuild::class)->latestOfMany();
    }

    public function managedApp(): HasOne
    {
        return $this->hasOne(ManagedApp::class, 'app_project_id');
    }
}
