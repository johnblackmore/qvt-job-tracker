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
#[Description('Update which AI model config is assigned to each assistant. Pass null or omit a field to keep the current assignment. Requires confirmation.')]
class UpdateAiAssistantConfigSettingsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'chat_agent_config_id' => $schema->integer()
                ->description('The AI model config ID to assign to the Chat Agent assistant. Pass 0 or omit to use env defaults.')
                ->nullable(),
            'product_url_extractor_config_id' => $schema->integer()
                ->description('The AI model config ID to assign to the Product URL Extractor assistant. Pass 0 or omit to use env defaults.')
                ->nullable(),
            'enquiry_draft_assistant_config_id' => $schema->integer()
                ->description('The AI model config ID to assign to the Enquiry Draft Assistant. Pass 0 or omit to use env defaults.')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
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

    protected function resolveConfigName(?int $configId): string
    {
        if (! $configId) {
            return 'env defaults';
        }

        $config = AiModelConfig::find($configId);

        return $config ? "{$config->label} ({$config->provider}/{$config->model})" : 'unknown config';
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'chat_agent_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'product_url_extractor_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
            'enquiry_draft_assistant_config_id' => ['nullable', 'integer', 'exists:ai_model_configs,id'],
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

        $current = app(AiAssistantConfigSettings::class);
        $chatAgent = $validated['chat_agent_config_id'] ?? $current->chat_agent_config_id;
        $urlExtractor = $validated['product_url_extractor_config_id'] ?? $current->product_url_extractor_config_id;
        $draftAssistant = $validated['enquiry_draft_assistant_config_id'] ?? $current->enquiry_draft_assistant_config_id;

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will update assistant config assignments.\n\n".
                    "Chat Agent → {$this->resolveConfigName($chatAgent)}\n".
                    "URL Extractor → {$this->resolveConfigName($urlExtractor)}\n".
                    "Enquiry Draft Assistant → {$this->resolveConfigName($draftAssistant)}\n\n".
                    'Is that correct?',
                'assignments' => [
                    'chat_agent' => $chatAgent ? $this->resolveConfigName($chatAgent) : 'env defaults',
                    'product_url_extractor' => $urlExtractor ? $this->resolveConfigName($urlExtractor) : 'env defaults',
                    'enquiry_draft_assistant' => $draftAssistant ? $this->resolveConfigName($draftAssistant) : 'env defaults',
                ],
            ]);
        }

        $current->chat_agent_config_id = $chatAgent;
        $current->product_url_extractor_config_id = $urlExtractor;
        $current->enquiry_draft_assistant_config_id = $draftAssistant;
        $current->save();

        return Response::structured([
            'status' => 'completed',
            'message' => "I have updated the assistant config assignments.\n\n".
                "Chat Agent → {$this->resolveConfigName($chatAgent)}\n".
                "URL Extractor → {$this->resolveConfigName($urlExtractor)}\n".
                "Enquiry Draft Assistant → {$this->resolveConfigName($draftAssistant)}",
            'assignments' => [
                'chat_agent' => $chatAgent ? $this->resolveConfigName($chatAgent) : 'env defaults',
                'product_url_extractor' => $urlExtractor ? $this->resolveConfigName($urlExtractor) : 'env defaults',
                'enquiry_draft_assistant' => $draftAssistant ? $this->resolveConfigName($draftAssistant) : 'env defaults',
            ],
        ]);
    }
}
