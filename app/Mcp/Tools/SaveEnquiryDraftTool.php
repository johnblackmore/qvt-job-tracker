<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use App\Models\EnquiryReply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Save an AI-generated or manually composed draft reply for an enquiry without sending. The draft is stored as a draft reply and can be reviewed and sent later. Requires confirmation.')]
class SaveEnquiryDraftTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID')
                ->required(),
            'subject' => $schema->string()
                ->description('Reply subject line')
                ->nullable(),
            'body' => $schema->string()
                ->description('Reply body text')
                ->required(),
            'ai_draft_data' => $schema->string()
                ->description('Optional JSON string of the original AI output for audit')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and save the draft.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the draft in staff admin')->nullable(),
            'reply' => $schema->object([
                'id' => $schema->integer(),
                'subject' => $schema->string()->nullable(),
                'body' => $schema->string(),
                'status' => $schema->string(),
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
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'ai_draft_data' => ['nullable', 'string'],
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

        $enquiry = Enquiry::findOrFail($validated['enquiry_id']);

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => 'I will save a draft reply for this enquiry. It will be available for review and editing before sending.',
                'data' => [
                    'enquiry_id' => $enquiry->id,
                    'subject' => $validated['subject'] ?? null,
                    'body' => $validated['body'],
                ],
            ]);
        }

        $aiDraftData = null;
        if (! empty($validated['ai_draft_data'])) {
            $decoded = json_decode($validated['ai_draft_data'], true);
            $aiDraftData = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $reply = EnquiryReply::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $request->user()?->id,
            'direction' => 'outbound',
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'status' => 'draft',
            'ai_draft_data' => $aiDraftData,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Draft reply saved. Review and send it from the enquiry page.',
            'url' => route('enquiries.show', $enquiry),
            'reply' => [
                'id' => $reply->id,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'status' => $reply->status,
            ],
        ]);
    }
}
