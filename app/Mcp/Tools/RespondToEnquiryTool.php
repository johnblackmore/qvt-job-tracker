<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Mark an enquiry as responded. Stamps responded_at automatically if not already set. Requires confirmation.')]
class RespondToEnquiryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The enquiry ID to mark as responded')
                ->required(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the action after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
            'enquiry' => $schema->object([
                'id' => $schema->integer(),
                'status' => $schema->string(),
                'responded_at' => $schema->string()->nullable(),
                'updated_at' => $schema->string()->nullable(),
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
            'id' => ['required', 'integer', 'exists:enquiries,id'],
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

        $enquiry = Enquiry::findOrFail($validated['id']);

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will mark enquiry as responded.\n\nSubject: ".($enquiry->subject ?? '(no subject)')."\nCurrent status: {$enquiry->status}\n\nIs that correct?",
                'data' => [
                    'id' => $enquiry->id,
                    'subject' => $enquiry->subject,
                    'current_status' => $enquiry->status,
                ],
            ]);
        }

        $updateData = ['status' => 'responded'];

        if ($enquiry->responded_at === null) {
            $updateData['responded_at'] = now();
        }

        $enquiry->update($updateData);
        $enquiry->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => 'Enquiry marked as responded.'.($enquiry->responded_at ? ' Responded at: '.$enquiry->responded_at->toDateTimeString().'.' : ''),
            'url' => route('enquiries.edit', $enquiry),
            'enquiry' => [
                'id' => $enquiry->id,
                'status' => $enquiry->status,
                'responded_at' => $enquiry->responded_at?->toIso8601String(),
                'updated_at' => $enquiry->updated_at->toIso8601String(),
            ],
        ]);
    }
}
