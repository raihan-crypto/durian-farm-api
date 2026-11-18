<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Tree Information (QR Code)
    |--------------------------------------------------------------------------
    |
    | Fields that may be exposed on the public tree page reached by scanning
    | a tree QR Code (SRS 10.2 & 10.3). Personal data of owners, farmers,
    | phone numbers, accounts and exact coordinates are never exposed.
    |
    */

    'public_tree' => [
        'available_fields' => [
            'tree_code',
            'tree_name',
            'variety',
            'garden',
            'general_location',
            'planted_at',
            'age',
            'status',
            'photos',
            'latest_measurement',
        ],

        'default_fields' => [
            'tree_code',
            'tree_name',
            'variety',
            'garden',
            'general_location',
            'photos',
        ],

        'path_prefix' => 't',

        'rate_limit_per_minute' => (int) env('PUBLIC_TREE_RATE_LIMIT', 30),

        'max_public_photos' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Photo Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'disk' => env('DURIAN_UPLOAD_DISK', 'public'),
        'max_kilobytes' => (int) env('DURIAN_UPLOAD_MAX_KB', 5120),
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plantation Activity Reference Options (SRS Bab 5)
    |--------------------------------------------------------------------------
    */

    'activities' => [
        'fertilization_methods' => [
            'tabur' => 'Tabur',
            'kocor' => 'Kocor',
            'semprot_daun' => 'Semprot Daun',
            'lainnya' => 'Lainnya',
        ],

        'flowering_statuses' => [
            'mulai' => 'Mulai Berbunga',
            'berbunga' => 'Berbunga Penuh',
            'selesai' => 'Selesai Berbunga',
        ],

        'fruit_development_stages' => [
            'pentil' => 'Pembentukan Bakal Buah / Pentil',
            'pembesaran' => 'Pembesaran Buah',
            'pematangan' => 'Pematangan',
            'siap_panen' => 'Siap Panen',
            'lainnya' => 'Lainnya',
        ],
    ],

];
