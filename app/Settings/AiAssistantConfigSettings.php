<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AiAssistantConfigSettings extends Settings
{
    public ?int $chat_agent_config_id = null;

    public ?int $product_url_extractor_config_id = null;

    public static function group(): string
    {
        return 'ai_assistant_config';
    }
}
