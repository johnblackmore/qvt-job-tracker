<?php

namespace App\Services;

use App\Models\AiDraftGeneration;
use App\Models\AiModelConfig;
use App\Models\Enquiry;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\StructuredMode;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class EnquiryAiAssistantService
{
    public function generateDraft(Enquiry $enquiry, string $tone = 'professional', ?User $user = null, ?string $source = null): array
    {
        $user = $user ?? auth()->user();

        $fallback = config('ai.assistants.enquiry-draft-assistant', [
            'provider' => config('ai.default_provider', 'opencode'),
            'model' => config('ai.default_model', 'deepseek-v4-flash-free'),
            'temperature' => 0.3,
            'max_tokens' => 2048,
        ]);

        $settings = app(AiAssistantConfigSettings::class);
        $configRecord = $settings->enquiry_draft_assistant_config_id
            ? AiModelConfig::find($settings->enquiry_draft_assistant_config_id)
            : null;

        $provider = $configRecord?->provider ?? $fallback['provider'];
        $model = $configRecord?->model ?? $fallback['model'];

        $generation = AiDraftGeneration::create([
            'user_id' => $user?->id,
            'enquiry_id' => $enquiry->id,
            'assistant_name' => 'enquiry-draft-assistant',
            'tone' => $tone,
            'trigger_source' => $source,
            'provider' => $provider,
            'model' => $model,
            'prompt_data' => json_encode([
                'tone' => $tone,
                'enquiry_id' => $enquiry->id,
                'trigger_source' => $source,
            ]),
            'status' => 'processing',
        ]);

        try {
            $renderedPrompt = view('ai.prompts.enquiry-draft-assistant', [
                'enquiry' => $enquiry,
                'tone' => $tone,
            ])->render();

            $response = Prism::structured()
                ->using($provider, $model)
                ->withSystemPrompt($renderedPrompt)
                ->withSchema(new ObjectSchema(
                    name: 'draft_response',
                    description: 'AI-generated draft response for a customer enquiry',
                    properties: [
                        new StringSchema('summary', 'Brief summary of what the customer needs'),
                        new ArraySchema('suggested_next_steps', 'Suggested next steps for the staff to take', new StringSchema('step', 'A suggested next step')),
                        new StringSchema('draft_subject', 'Draft reply subject line'),
                        new StringSchema('draft_body', 'Draft reply body text'),
                        new StringSchema('confidence', 'Confidence level of the draft: high, medium, or low'),
                        new ArraySchema('knowledge_gaps', 'Missing information that would help provide a better response', new StringSchema('gap', 'A knowledge gap')),
                    ],
                ))
                ->usingStructuredMode(StructuredMode::Auto)
                ->usingTemperature($fallback['temperature'])
                ->withMaxTokens($fallback['max_tokens'])
                ->asStructured();

            $data = $response->structured ?? [];

            $generation->update([
                'status' => 'completed',
                'raw_response' => $response->text,
                'summary' => $data['summary'] ?? null,
                'draft_subject' => $data['draft_subject'] ?? null,
                'draft_body' => $data['draft_body'] ?? null,
                'confidence' => $data['confidence'] ?? null,
                'suggested_next_steps' => $data['suggested_next_steps'] ?? null,
                'knowledge_gaps' => $data['knowledge_gaps'] ?? null,
                'input_tokens' => $response->usage?->promptTokens,
                'output_tokens' => $response->usage?->completionTokens,
            ]);

            return array_merge($this->validateResult($data), [
                'draft_generation_id' => $generation->id,
            ]);
        } catch (\Throwable $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('AI draft generation failed', [
                'enquiry_id' => $enquiry->id,
                'draft_generation_id' => $generation->id,
                'error' => $e->getMessage(),
            ]);

            return array_merge($this->validateResult([]), [
                'error' => $e->getMessage(),
                'draft_generation_id' => $generation->id,
            ]);
        }
    }

    private function validateResult(array $result): array
    {
        $defaults = [
            'summary' => '',
            'suggested_next_steps' => [],
            'draft_subject' => '',
            'draft_body' => '',
            'confidence' => 'low',
            'knowledge_gaps' => [],
        ];

        return array_merge($defaults, $result);
    }
}
