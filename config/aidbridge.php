<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Internal service base URL
    |--------------------------------------------------------------------------
    |
    | Base address the module-to-module web service clients call. Modules talk
    | to each other over HTTP rather than in-process, so each could be deployed
    | as a separate application without any consumer changing. Point this at the
    | relevant host per module once they are split.
    |
    */

    'internal_api_base' => env('INTERNAL_API_BASE', env('APP_URL', 'http://localhost:8000')),

    /*
    |--------------------------------------------------------------------------
    | Malaysian states and federal territories
    |--------------------------------------------------------------------------
    |
    | Used by the registration and application forms. Kept here rather than
    | repeated in three Blade files so the list has one definition.
    |
    */

    'states' => [
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Perak',
        'Perlis',
        'Pulau Pinang',
        'Sabah',
        'Sarawak',
        'Selangor',
        'Terengganu',
        'Kuala Lumpur',
        'Labuan',
        'Putrajaya',
    ],

];
