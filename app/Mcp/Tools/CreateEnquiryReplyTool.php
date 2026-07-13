<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use App\Services\EnquiryReplyService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Send a reply to a customer enquiry via email. Preview first, then confirm to send. The reply is logged in the conversation thread.')]
class CreateEnquiryReplyTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to reply to')
                ->required(),
            'subject' => $schema->string()
                ->description('Email subject line. Defaults to "Re: [original subject]"')
                ->nullable(),
            'body' => $schema->string()
                ->description('Reply body text')
                ->required(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without sending.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and send the reply after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the enquiry in staff admin')->nullable(),
            'reply' => $schema->object([
                'id' => $schema->integer(),
                'subject' => $schema->string()->nullable(),
                'body' => $schema->string(),
                'status' => $schema->string(),
                'sent_at' => $schema->string()->nullable(),
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

        $enquiry = Enquiry::with('customer')->findOrFail($validated['enquiry_id']);
        $toEmail = $enquiry->email ?? $enquiry->customer?->email;

        $subject = $validated['subject'] ?? 'Re: '.($enquiry->subject ?? 'Your Enquiry');

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => sprintf(
                    "I will send a reply to the enquiry.\n\nTo: %s\nSubject: %s\nBody:\n%s\n\nIs that correct?",
                    $toEmail ?? '(no email — set an email on this enquiry first)',
                    $subject,
                    $validated['body']
                ),
                'data' => [
                    'enquiry_id' => $enquiry->id,
                    'to_email' => $toEmail,
                    'subject' => $subject,
                    'body' => $validated['body'],
                ],
            ]);
        }

        if (! $toEmail) {
            return Response::error(
                'This enquiry has no email address. Link it to a customer with an email address, or set an email on the enquiry first.'
            );
        }

        $service = app(EnquiryReplyService::class);
        $reply = $service->send($enquiry, [
            'subject' => $subject,
            'body' => $validated['body'],
        ], $request->user()?->id);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Reply sent successfully.'.($reply->sent_at ? ' Sent at: '.$reply->sent_at->toDateTimeString().'.' : ''),
            'url' => route('enquiries.show', $enquiry),
            'reply' => [
                'id' => $reply->id,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'status' => $reply->status,
                'sent_at' => $reply->sent_at?->toIso8601String(),
            ],
        ]);
    }
}
