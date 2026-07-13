<?php

namespace App\Mcp\Tools\AiConfig;

use App\Models\AiModelConfig;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new AI model config (provider/model pairing). Requires confirmation.')]
class CreateAiModelConfigTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'label' => $schema->string()
                ->description('A human-readable label for this config, e.g. "OpenCode DeepSeek Flash"')
                ->required(),
            'provider' => $schema->string()
                ->description('The LLM provider key (e.g. opencode, openai, anthropic, deepseek). Must be a configured provider.')
                ->required(),
            'model' => $schema->string()
                ->description('The model name to use with this provider, e.g. "gpt-4o", "deepseek-v4-flash-free", "claude-3-5-sonnet"')
                ->required(),
            'description' => $schema->string()
                ->description('Optional notes about this config')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute after preview.')
                ->default(false),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $providers = array_keys(config('prism.providers'));

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:'.implode(',', $providers)],
            'model' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error(
                'This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed.'
            );
        }

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will create a new AI model config.\n\nLabel: {$validated['label']}\nProvider: {$validated['provider']}\nModel: {$validated['model']}\n\nIs that correct?",
                'config' => [
                    'label' => $validated['label'],
                    'provider' => $validated['provider'],
                    'model' => $validated['model'],
                    'description' => $validated['description'] ?? null,
                ],
            ]);
        }

        $config = AiModelConfig::create([
            'label' => $validated['label'],
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'description' => $validated['description'] ?? null,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new AI model config: {$config->label} ({$config->provider}/{$config->model}).",
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
