<?php

namespace App\Livewire\AiModelConfigs;

use App\Models\AiModelConfig;
use App\Settings\AiAssistantConfigSettings;
use Livewire\Component;

class AiModelConfigList extends Component
{
    public function delete(int $id): void
    {
        $config = AiModelConfig::find($id);

        if (! $config) {
            return;
        }

        $settings = app(AiAssistantConfigSettings::class);
        $assignedTo = [];

        if ($settings->chat_agent_config_id === $id) {
            $assignedTo[] = 'Chat Agent';
            $settings->chat_agent_config_id = null;
        }

        if ($settings->product_url_extractor_config_id === $id) {
            $assignedTo[] = 'Product URL Extractor';
            $settings->product_url_extractor_config_id = null;
        }

        $config->delete();
        $settings->save();

        if (! empty($assignedTo)) {
            session()->flash('flash', [
                'type' => 'warning',
                'message' => 'Config deleted. Was assigned to '.implode(' and ', $assignedTo).' — these assistants will use env defaults.',
            ]);
        } else {
            session()->flash('flash', [
                'type' => 'success',
                'message' => 'AI model config deleted.',
            ]);
        }
    }

    public function assignToAssistant(int $configId, string $assistant): void
    {
        $config = AiModelConfig::findOrFail($configId);

        $settings = app(AiAssistantConfigSettings::class);

        match ($assistant) {
            'chat-agent' => $settings->chat_agent_config_id = $configId,
            'product-url-extractor' => $settings->product_url_extractor_config_id = $configId,
            default => null,
        };

        $settings->save();

        session()->flash('flash', [
            'type' => 'success',
            'message' => "{$config->label} assigned to {$assistant}.",
        ]);
    }

    public function render()
    {
        $configs = AiModelConfig::orderBy('label')->get();

        $settings = app(AiAssistantConfigSettings::class);

        return view('livewire.ai-model-configs.ai-model-config-list', [
            'configs' => $configs,
            'assignedChatAgent' => $settings->chat_agent_config_id,
            'assignedUrlExtractor' => $settings->product_url_extractor_config_id,
        ]);
    }
}
