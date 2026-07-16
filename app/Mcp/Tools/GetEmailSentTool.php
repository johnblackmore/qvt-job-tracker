<?php

namespace App\Mcp\Tools;

use App\Models\EmailSent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get details of a specific sent email by its ID. Returns full email details with webhook tracking timestamps and correlation data.')]
class GetEmailSentTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The email ID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'email' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:emails_sent,id'],
        ]);

        $email = EmailSent::with(['customer', 'quote', 'template'])->findOrFail($validated['id']);

        $timestamps = [];
        foreach (['delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'spam_complaint_at'] as $field) {
            if ($email->$field) {
                $timestamps[$field] = $email->$field?->toIso8601String();
            }
        }

        return Response::structured([
            'status' => 'completed',
            'message' => 'Retrieved email #'.$email->id.' sent to '.$email->to_email.'.',
            'email' => [
                'id' => $email->id,
                'to_email' => $email->to_email,
                'subject' => $email->subject,
                'body_html' => $email->body_html,
                'status' => $email->status,
                'postmark_message_id' => $email->postmark_message_id,
                'customer_id' => $email->customer_id,
                'customer_name' => $email->customer?->name,
                'quote_id' => $email->quote_id,
                'template_id' => $email->template_id,
                'template_name' => $email->template?->name,
                'bounce_type' => $email->bounce_type,
                'error_message' => $email->error_message,
                'timestamps' => $timestamps,
                'sent_at' => $email->sent_at?->toIso8601String(),
                'created_at' => $email->created_at?->toIso8601String(),
            ],
        ]);
    }
}
