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

    'open_meteo' => [
        'geocoding_url' => 'https://geocoding-api.open-meteo.com/v1/search',
        'forecast_url' => 'https://api.open-meteo.com/v1/forecast',
        'timeout' => 8,
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
        // tried in order when the model above answers 404 (not available for this key or renamed)
        'fallback_models' => ['gemini-3.1-flash-image-preview', 'gemini-2.5-flash-image'],
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

];
