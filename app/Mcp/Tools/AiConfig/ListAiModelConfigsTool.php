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
#[Description('List all AI model configs (provider/model pairings). Returns each config with label, provider, model, and description.')]
class ListAiModelConfigsTool extends Tool
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
        $configs = AiModelConfig::orderBy('label')->get();

        $data = $configs->map(function (AiModelConfig $config) {
            return [
                'id' => $config->id,
                'label' => $config->label,
                'provider' => $config->provider,
                'model' => $config->model,
                'description' => $config->description,
                'url' => route('admin.ai.configs.edit', $config),
            ];
        });

        $count = $configs->count();

        return Response::structured([
            'status' => 'completed',
            'message' => "Found {$count} AI model config".($count !== 1 ? 's' : '').'.',
            'data' => $data,
        ]);
    }
}
