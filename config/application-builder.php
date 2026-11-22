<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Builder Paths
    |--------------------------------------------------------------------------
    |
    | Konfigurasi path untuk integrasi Xoose–LoveYou:
    | - template : Path template asli LoveYou (harus read-only)
    | - engine   : Path Python build engine
    | - workspace: Direktori kerja terisolasi per proyek/build
    | - builds   : Direktori artefak hasil build (.exe / distribusi)
    | - logs     : Direktori penyimpanan log proses build
    | - temp     : Direktori file sementara
    |
    */
    'paths' => [
        'template' => env(
            'APP_BUILDER_TEMPLATE_PATH',
            'D:/KULIAH/KODINGAN/screenshotusingpy'
        ),
        'engine' => env(
            'APP_BUILDER_ENGINE_PATH',
            'D:/KULIAH/KODINGAN/xoose-loveyou/builder'
        ),
        'workspace' => env(
            'APP_BUILDER_WORKSPACE_PATH',
            'D:/KULIAH/KODINGAN/xoose-loveyou/workspace'
        ),
        'builds' => env(
            'APP_BUILDER_OUTPUT_PATH',
            'D:/KULIAH/KODINGAN/xoose-loveyou/builds'
        ),
        'logs' => env(
            'APP_BUILDER_LOG_PATH',
            'D:/KULIAH/KODINGAN/xoose-loveyou/logs'
        ),
        'temp' => env(
            'APP_BUILDER_TEMP_PATH',
            'D:/KULIAH/KODINGAN/xoose-loveyou/temp'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Binary & Timeout
    |--------------------------------------------------------------------------
    */
    'python' => env('APP_BUILDER_PYTHON', 'python'),
    'build_timeout' => (int) env('APP_BUILDER_TIMEOUT', 3600),
];
