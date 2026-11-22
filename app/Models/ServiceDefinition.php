<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_key',
        'display_name',
        'description',
        'supported_actions',
        'configuration_schema',
        'permission_requirements',
        'is_system',
    ];

    protected $casts = [
        'supported_actions' => 'array',
        'configuration_schema' => 'array',
        'permission_requirements' => 'array',
        'is_system' => 'boolean',
    ];

    public function supportsAction(string $action): bool
    {
        $normalized = strtolower(trim($action));
        if ($normalized === 'apply_config') {
            return true;
        }

        $supported = array_map('strtolower', $this->supported_actions ?? []);

        return in_array($normalized, $supported, true);
    }
}
