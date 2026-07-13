<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai_assistant_config.chat_agent_config_id', null);
        $this->migrator->add('ai_assistant_config.product_url_extractor_config_id', null);
    }
};
