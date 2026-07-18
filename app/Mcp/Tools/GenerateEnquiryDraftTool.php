<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use App\Services\EnquiryAiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Generate an AI draft response for a customer enquiry. The AI analyses the enquiry and produces a draft reply, suggested next steps, and identifies knowledge gaps. This is a READ-ONLY tool — nothing is sent or saved.')]
class GenerateEnquiryDraftTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to generate a draft for')
                ->required(),
            'tone' => $schema->string()
                ->description('Writing tone for the draft')
                ->enum(['professional', 'casual'])
                ->default('professional'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['completed', 'error'])->description('Response status')->required(),
            'message' => $schema->string()->description('Human-readable result message')->required(),
            'draft' => $schema->object([
                'summary' => $schema->string(),
                'suggested_next_steps' => $schema->array($schema->string()),
                'draft_subject' => $schema->string(),
                'draft_body' => $schema->string(),
                'confidence' => $schema->string(),
                'knowledge_gaps' => $schema->array($schema->string()),
            ])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'enquiry_id' => ['required', 'integer', 'exists:enquiries,id'],
            'tone' => ['nullable', 'in:professional,casual'],
        ]);

        $enquiry = Enquiry::with('customer')->findOrFail($validated['enquiry_id']);
        $tone = $validated['tone'] ?? 'professional';

        $service = app(EnquiryAiAssistantService::class);
        $draft = $service->generateDraft($enquiry, $tone, $request->user(), 'mcp');

        if (! empty($draft['error'])) {
            return Response::structured([
                'status' => 'error',
                'message' => 'AI draft generation failed: '.$draft['error'].'. Please check the AI model configuration and try again.',
                'draft' => null,
            ]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => sprintf(
                'AI draft generated with %s confidence.%s%s',
                $draft['confidence'] ?? 'low',
                ! empty($draft['knowledge_gaps']) ? ' Knowledge gaps identified: '.implode(', ', $draft['knowledge_gaps']).'.' : '',
                ! empty($draft['suggested_next_steps']) ? ' Suggested next steps: '.implode(', ', array_slice($draft['suggested_next_steps'], 0, 3)).'.' : ''
            ),
            'draft' => [
                'summary' => $draft['summary'] ?? '',
                'suggested_next_steps' => $draft['suggested_next_steps'] ?? [],
                'draft_subject' => $draft['draft_subject'] ?? '',
                'draft_body' => $draft['draft_body'] ?? '',
                'confidence' => $draft['confidence'] ?? 'low',
                'knowledge_gaps' => $draft['knowledge_gaps'] ?? [],
            ],
        ]);
    }
}
