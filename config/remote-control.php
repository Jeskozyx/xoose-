<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Xoose–LoveYou Centralized Remote Service Manager Configuration
    |--------------------------------------------------------------------------
    */
    'heartbeat_timeout_seconds' => (int) env('DEVICE_HEARTBEAT_TIMEOUT_SECONDS', 90),

    'command_default_ttl_seconds' => (int) env('COMMAND_DEFAULT_TTL_SECONDS', 300),

    'command_max_ttl_seconds' => (int) env('COMMAND_MAX_TTL_SECONDS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Strict Allowlist of Remote Command Actions
    |--------------------------------------------------------------------------
    | Arbitrary shell / exec / script actions are NEVER permitted.
    */
    'allowed_actions' => [
        'start',
        'stop',
        'pause',
        'resume',
        'restart',
        'apply_config',
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard UI Statuses
    |--------------------------------------------------------------------------
    */
    'device_statuses' => ['Online', 'Offline', 'Pending', 'Revoked'],

    'service_statuses' => ['Running', 'Stopped', 'Paused', 'Failed', 'Unknown'],

    'command_statuses' => ['pending', 'delivered', 'acknowledged', 'succeeded', 'failed', 'expired', 'cancelled'],

    /*
    |--------------------------------------------------------------------------
    | Release Signing Key (Server-Side Only — Never Embedded in EXE)
    |--------------------------------------------------------------------------
    */
    'release_signing_key' => env('REMOTE_RELEASE_SIGNING_KEY', env('APP_KEY')),
];
