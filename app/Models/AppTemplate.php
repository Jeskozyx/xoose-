<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'version',
        'source_path',
        'manifest_path',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(AppProject::class);
    }
}
