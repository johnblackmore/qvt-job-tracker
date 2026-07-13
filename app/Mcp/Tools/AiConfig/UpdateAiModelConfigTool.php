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
#[Description('Update an existing AI model config (provider/model pairing). Requires confirmation.')]
class UpdateAiModelConfigTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The AI model config ID to update.')
                ->required(),
            'label' => $schema->string()
                ->description('A human-readable label for this config.')
                ->nullable(),
            'provider' => $schema->string()
                ->description('The LLM provider key (e.g. opencode, openai, anthropic). Must be a configured provider.')
                ->nullable(),
            'model' => $schema->string()
                ->description('The model name to use with this provider.')
                ->nullable(),
            'description' => $schema->string()
                ->description('Optional notes about this config.')
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
            'id' => ['required', 'integer', 'exists:ai_model_configs,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'in:'.implode(',', $providers)],
            'model' => ['nullable', 'string', 'max:100'],
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

        $config = AiModelConfig::findOrFail($validated['id']);

        if ($isPreview && ! $isConfirmed) {
            $newLabel = $validated['label'] ?? null;
            $newProvider = $validated['provider'] ?? null;
            $newModel = $validated['model'] ?? null;

            return Response::structured([
                'status' => 'preview',
                'message' => "I will update the AI model config \"{$config->label}\".\n\nCurrent: {$config->provider}/{$config->model}\n".
                    ($newLabel ? "New label: {$newLabel}\n" : '').
                    ($newProvider ? "New provider: {$newProvider}\n" : '').
                    ($newModel ? "New model: {$newModel}\n" : '').
                    "\nIs that correct?",
                'config' => [
                    'id' => $config->id,
                    'label' => $validated['label'] ?? $config->label,
                    'provider' => $validated['provider'] ?? $config->provider,
                    'model' => $validated['model'] ?? $config->model,
                    'description' => $validated['description'] ?? $config->description,
                ],
            ]);
        }

        $updateData = array_filter([
            'label' => $validated['label'] ?? null,
            'provider' => $validated['provider'] ?? null,
            'model' => $validated['model'] ?? null,
            'description' => $validated['description'] ?? null,
        ], fn ($value) => $value !== null);

        $config->update($updateData);

        return Response::structured([
            'status' => 'completed',
            'message' => "I have updated the AI model config \"{$config->label}\".",
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
