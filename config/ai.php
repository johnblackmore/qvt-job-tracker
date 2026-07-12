<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'opencode'),
    'default_model' => env('AI_DEFAULT_MODEL', 'deepseek-v4-flash-free'),

    'assistants' => [
        'product-url-extractor' => [
            'provider' => env('AI_URL_EXTRACTOR_PROVIDER', 'opencode'),
            'model' => env('AI_URL_EXTRACTOR_MODEL', 'deepseek-v4-flash-free'),
            'temperature' => 0.1,
            'max_tokens' => 2048,
        ],
    ],
];
