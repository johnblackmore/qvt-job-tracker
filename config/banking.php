<?php

return [

    'default' => env('BANKING_DEFAULT_PROVIDER', 'monzo'),

    'providers' => [
        'monzo' => [
            'client_id' => env('MONZO_CLIENT_ID'),
            'client_secret' => env('MONZO_CLIENT_SECRET'),
            'redirect_uri' => env('MONZO_REDIRECT_URI'),
        ],
    ],

];
