<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\EnquiryReply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Add an internal note to an enquiry. Internal notes are only visible to staff, are never sent to the customer, and do not trigger any email. Use this to record context, reminders, or observations.')]
class AddInternalNoteTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to add the note to')
                ->required(),
            'body' => $schema->string()
                ->description('The internal note content')
                ->required(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and save the note after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the enquiry in staff admin')->nullable(),
            'note' => $schema->object([
                'id' => $schema->integer(),
                'body' => $schema->string(),
                'staff_name' => $schema->string()->nullable(),
                'created_at' => $schema->string()->nullable(),
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
            'body' => ['required', 'string', 'max:5000'],
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
                'message' => sprintf(
                    "I will add an internal note to enquiry #%d.\n\nNote:\n%s\n\nThis note will NOT be sent to the customer. It is only visible to staff. Confirm to save.",
                    $enquiry->id,
                    $validated['body']
                ),
                'data' => [
                    'enquiry_id' => $enquiry->id,
                    'body' => $validated['body'],
                ],
            ]);
        }

        $reply = EnquiryReply::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $request->user()->id,
            'direction' => 'outbound',
            'is_internal' => true,
            'body' => $validated['body'],
            'status' => 'saved',
        ]);

        EnquiryActivityLog::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $request->user()->id,
            'action' => 'note_added',
            'description' => 'Added internal note',
            'metadata' => ['note_id' => $reply->id],
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Internal note added to enquiry #'.$enquiry->id.'. It is only visible to staff and was not sent to the customer.',
            'url' => route('enquiries.show', $enquiry),
            'note' => [
                'id' => $reply->id,
                'body' => $reply->body,
                'staff_name' => $request->user()->name,
                'created_at' => $reply->created_at?->toIso8601String(),
            ],
        ]);
    }
}
