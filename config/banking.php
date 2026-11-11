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
];
