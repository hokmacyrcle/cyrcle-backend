<?php

return [
    /*
    | Which live bank feed to use: 'acsiss' (SISS Data Services, once its keys are added),
    | 'demo' (a clearly labelled simulation for showing the screens) or empty (feed switched off).
    | Set BANK_FEED_DRIVER in .env. Leave it empty in production until the real provider is connected.
    */
    'feed_driver' => env('BANK_FEED_DRIVER', ''),

    'acsiss' => [
        'base_url' => env('ACSISS_BASE_URL'),
        'client_id' => env('ACSISS_CLIENT_ID'),
        'client_secret' => env('ACSISS_CLIENT_SECRET'),
    ],

    /*
    | Reading receipts automatically (supplier, date, total, GST) with Claude.
    | Leave ANTHROPIC_API_KEY empty to switch it off: receipts are then filled in by hand.
    | When on, the receipt file is sent to Anthropic to be read, so tell your clients.
    */
    'receipt_scan' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('RECEIPT_SCAN_MODEL', 'claude-haiku-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],
];
