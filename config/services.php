<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'moadian' => [
        'driver' => env('MOADIAN_DRIVER', 'demo'),
        'base_url' => env('MOADIAN_BASE_URL', 'https://tp.tax.gov.ir/req/api/self-tsp'),
        'fiscal_id' => env('MOADIAN_FISCAL_ID'),
        'seller_economic_code' => env('MOADIAN_SELLER_ECONOMIC_CODE'),
        'seller_branch_code' => env('MOADIAN_SELLER_BRANCH_CODE'),
        'private_key_path' => env('MOADIAN_PRIVATE_KEY_PATH'),
        'ca_bundle_path' => env('MOADIAN_CA_BUNDLE_PATH'),
        'default_measurement_unit_code' => env('MOADIAN_DEFAULT_MEASUREMENT_UNIT_CODE'),
        'connect_timeout' => (int) env('MOADIAN_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('MOADIAN_TIMEOUT', 20),
    ],

];
