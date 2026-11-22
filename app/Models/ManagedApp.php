<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagedApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_project_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'current_version',
        'build_status',
        'default_config',
        'is_active',
    ];

    protected $casts = [
        'default_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(AppDevice::class, 'managed_app_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DeviceEnrollment::class, 'managed_app_id');
    }

    public function configs(): HasMany
    {
        return $this->hasMany(ServiceConfig::class, 'managed_app_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(AppRelease::class, 'managed_app_id')->orderByDesc('id');
    }

    public function activeRelease(): HasOne
    {
        return $this->hasOne(AppRelease::class, 'managed_app_id')->where('is_active', true)->latestOfMany();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class, 'managed_app_id')->orderByDesc('id');
    }
}
