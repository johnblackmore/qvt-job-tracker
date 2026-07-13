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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Delete an AI model config. If the config is currently assigned to an assistant, the assistant will fall back to env defaults. Requires confirmation.')]
class DeleteAiModelConfigTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The AI model config ID to delete.')
                ->required(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without deleting.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and delete after preview.')
                ->default(false),
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

        $settings = app(AiAssistantConfigSettings::class);
        $assignedTo = [];

        if ($settings->chat_agent_config_id === $config->id) {
            $assignedTo[] = 'Chat Agent';
        }

        if ($settings->product_url_extractor_config_id === $config->id) {
            $assignedTo[] = 'Product URL Extractor';
        }

        if ($isPreview && ! $isConfirmed) {
            $message = "I will delete the AI model config \"{$config->label}\" ({$config->provider}/{$config->model}).";

            if (! empty($assignedTo)) {
                $message .= "\n\n⚠️ This config is currently assigned to: ".implode(', ', $assignedTo).'. They will fall back to env defaults.';
            }

            $message .= "\n\nAre you sure?";

            return Response::structured([
                'status' => 'preview',
                'message' => $message,
                'config' => [
                    'id' => $config->id,
                    'label' => $config->label,
                    'provider' => $config->provider,
                    'model' => $config->model,
                ],
                'assigned_to' => $assignedTo,
            ]);
        }

        if (! empty($assignedTo)) {
            if ($settings->chat_agent_config_id === $config->id) {
                $settings->chat_agent_config_id = null;
            }
            if ($settings->product_url_extractor_config_id === $config->id) {
                $settings->product_url_extractor_config_id = null;
            }
            $settings->save();
        }

        $config->delete();

        $message = "I have deleted the AI model config \"{$config->label}\".";

        if (! empty($assignedTo)) {
            $message .= ' It was assigned to '.implode(' and ', $assignedTo).' — they will now use env defaults.';
        }

        return Response::structured([
            'status' => 'completed',
            'message' => $message,
        ]);
    }
}
