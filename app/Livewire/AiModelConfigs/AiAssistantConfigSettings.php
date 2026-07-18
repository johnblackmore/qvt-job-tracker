<?php

namespace App\Livewire\AiModelConfigs;

use App\Models\AiModelConfig;
use App\Settings\AiAssistantConfigSettings as Settings;
use Livewire\Component;

class AiAssistantConfigSettings extends Component
{
    public ?int $chat_agent_config_id = null;

    public ?int $product_url_extractor_config_id = null;

    public ?int $enquiry_draft_assistant_config_id = null;

    public ?int $expenses_extractor_config_id = null;

    public ?int $expenses_extractor_vision_config_id = null;

    public function mount(): void
    {
        $settings = app(Settings::class);
        $this->chat_agent_config_id = $settings->chat_agent_config_id;
        $this->product_url_extractor_config_id = $settings->product_url_extractor_config_id;
        $this->enquiry_draft_assistant_config_id = $settings->enquiry_draft_assistant_config_id;
        $this->expenses_extractor_config_id = $settings->expenses_extractor_config_id;
        $this->expenses_extractor_vision_config_id = $settings->expenses_extractor_vision_config_id;
    }

    public function save(): void
    {
        $this->validate([
            'chat_agent_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'product_url_extractor_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'enquiry_draft_assistant_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'expenses_extractor_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'expenses_extractor_vision_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
        ]);

        $settings = app(Settings::class);
        $settings->chat_agent_config_id = $this->chat_agent_config_id;
        $settings->product_url_extractor_config_id = $this->product_url_extractor_config_id;
        $settings->enquiry_draft_assistant_config_id = $this->enquiry_draft_assistant_config_id;
        $settings->expenses_extractor_config_id = $this->expenses_extractor_config_id;
        $settings->expenses_extractor_vision_config_id = $this->expenses_extractor_vision_config_id;
        $settings->save();

        session()->flash('flash', [
            'type' => 'success',
            'message' => 'Assistant settings saved.',
        ]);
    }

    public function render()
    {
        return view('livewire.ai-model-configs.ai-assistant-config-settings', [
            'configs' => AiModelConfig::orderBy('label')->get(),
            'visionConfigs' => AiModelConfig::where('has_vision', true)->orderBy('label')->get(),
        ]);
    }
}
