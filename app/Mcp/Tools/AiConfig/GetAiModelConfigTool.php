<?php

namespace App\Mcp\Tools\AiConfig;

use App\Models\AiModelConfig;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a single AI model config by ID. Returns label, provider, model, and description.')]
class GetAiModelConfigTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The AI model config ID')
                ->required(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:ai_model_configs,id'],
        ]);

        $config = AiModelConfig::findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "AI model config: {$config->label} ({$config->provider}/{$config->model}).",
            'url' => route('admin.ai.configs.edit', $config),
            'config' => [
                'id' => $config->id,
                'label' => $config->label,
                'provider' => $config->provider,
                'model' => $config->model,
                'description' => $config->description,
            ],
        ]);
    }
}
