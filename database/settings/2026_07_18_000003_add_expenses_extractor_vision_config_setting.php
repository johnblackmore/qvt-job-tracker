<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai_assistant_config.expenses_extractor_vision_config_id', null);
    }

    public function down(): void
    {
        $this->migrator->delete('ai_assistant_config.expenses_extractor_vision_config_id');
    }
};
