<?php

namespace App\Mcp\Tools\AiConfig;

use App\Models\AiModelConfig;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Read the current assistant-to-config assignments. Shows which AI model config is assigned to each assistant.')]
class GetAiAssistantConfigSettingsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $settings = app(AiAssistantConfigSettings::class);

        $chatAgent = $settings->chat_agent_config_id
            ? AiModelConfig::find($settings->chat_agent_config_id)
            : null;

        $urlExtractor = $settings->product_url_extractor_config_id
            ? AiModelConfig::find($settings->product_url_extractor_config_id)
            : null;

        return Response::structured([
            'status' => 'completed',
            'message' => 'Current assistant configuration.',
            'assignments' => [
                'chat_agent' => $chatAgent
                    ? [
                        'id' => $chatAgent->id,
                        'label' => $chatAgent->label,
                        'provider' => $chatAgent->provider,
                        'model' => $chatAgent->model,
                        'url' => route('admin.ai.configs.edit', $chatAgent),
                    ]
                    : ['state' => 'using env defaults'],
                'product_url_extractor' => $urlExtractor
                    ? [
                        'id' => $urlExtractor->id,
                        'label' => $urlExtractor->label,
                        'provider' => $urlExtractor->provider,
                        'model' => $urlExtractor->model,
                        'url' => route('admin.ai.configs.edit', $urlExtractor),
                    ]
                    : ['state' => 'using env defaults'],
            ],
        ]);
    }
}
