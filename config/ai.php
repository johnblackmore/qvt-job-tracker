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

        'chat-agent' => [
            'provider' => env('AI_CHAT_PROVIDER', 'opencode'),
            'model' => env('AI_CHAT_MODEL', 'deepseek-v4-flash-free'),
            'temperature' => 0.3,
            'max_tokens' => 4096,
            'max_steps' => 15,
            'token_budget' => 32000,
            'system_prompt' => 'ai.prompts.chat-agent',
        ],

        'enquiry-draft-assistant' => [
            'provider' => env('AI_DRAFT_PROVIDER', 'opencode'),
            'model' => env('AI_DRAFT_MODEL', 'deepseek-v4-flash-free'),
            'temperature' => 0.3,
            'max_tokens' => 2048,
        ],
    ],
];
