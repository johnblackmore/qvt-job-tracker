<?php

namespace App\Livewire\AiModelConfigs;

use App\Models\AiModelConfig;
use Livewire\Component;

class AiModelConfigForm extends Component
{
    public ?AiModelConfig $config = null;

    public string $label = '';

    public string $provider = '';

    public string $model = '';

    public string $description = '';

    public bool $has_vision = false;

    public ?string $input_price = null;

    public ?string $output_price = null;

    public function mount(?int $aiModelConfig = null): void
    {
        if ($aiModelConfig) {
            $this->config = AiModelConfig::findOrFail($aiModelConfig);
            $this->label = $this->config->label;
            $this->provider = $this->config->provider;
            $this->model = $this->config->model;
            $this->description = $this->config->description ?? '';
            $this->has_vision = $this->config->has_vision ?? false;
            $this->input_price = $this->config->input_price !== null ? (string) $this->config->input_price : null;
            $this->output_price = $this->config->output_price !== null ? (string) $this->config->output_price : null;
        }
    }

    public function save(): void
    {
        $providers = array_keys(config('prism.providers'));

        $validated = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:'.implode(',', $providers)],
            'model' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'has_vision' => ['boolean'],
            'input_price' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'output_price' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
        ]);

        if ($this->config) {
            $this->config->update($validated);
            session()->flash('flash', ['type' => 'success', 'message' => 'AI model config updated.']);
        } else {
            AiModelConfig::create($validated);
            session()->flash('flash', ['type' => 'success', 'message' => 'AI model config created.']);
        }

        $this->redirect(route('admin.ai.configs.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.ai-model-configs.ai-model-config-form', [
            'providers' => array_keys(config('prism.providers')),
        ]);
    }
}
