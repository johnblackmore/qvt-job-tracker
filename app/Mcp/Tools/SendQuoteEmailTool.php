<?php

namespace App\Mcp\Tools;

use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Services\QuoteEmailService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Send a quote to the customer by email with a PDF attachment. Optionally uses an email template. Auto-advances quote from draft to sent. Requires confirmation.')]
class SendQuoteEmailTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'quote_id' => $schema->integer()
                ->description('The quote ID to send')
                ->required(),
            'template_id' => $schema->integer()
                ->description('Optional email template ID (must be active)')
                ->nullable(),
            'custom_message' => $schema->string()
                ->description('Optional custom message to include in the email body')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without sending.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the send after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
            'email_sent' => $schema->object([
                'id' => $schema->integer(),
                'to_email' => $schema->string(),
                'subject' => $schema->string(),
                'status' => $schema->string(),
                'sent_at' => $schema->string()->nullable(),
            ])->nullable(),
            'quote' => $schema->object([
                'id' => $schema->integer(),
                'reference_number' => $schema->string(),
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
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'custom_message' => ['nullable', 'string', 'max:10000'],
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

        $quote = Quote::with('customer')->findOrFail($validated['quote_id']);

        $template = null;
        if (! empty($validated['template_id'])) {
            $template = EmailTemplate::where('id', $validated['template_id'])
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return Response::error(
                    "Email template #{$validated['template_id']} was not found or is not active."
                );
            }
        }

        $customer = $quote->customer;

        if (! $customer || ! $customer->email) {
            return Response::error(
                'Customer has no email address. Quote cannot be sent.'
            );
        }

        $lineCount = $quote->lineItems()->count();

        if ($isPreview && ! $isConfirmed) {
            $templateName = $template ? $template->name : 'Default template';
            $customMsgPreview = ! empty($validated['custom_message'])
                ? "\nCustom message: ".Str::limit($validated['custom_message'], 100)
                : '';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will send quote {$quote->reference_number} to {$customer->name} <{$customer->email}>.\n\nTemplate: {$templateName}\nLine items: {$lineCount}\nGrand total: £".number_format($quote->grand_total, 2).$customMsgPreview."\n\nIs that correct?",
                'data' => [
                    'quote_id' => $quote->id,
                    'quote_reference' => $quote->reference_number,
                    'to_email' => $customer->email,
                    'customer_name' => $customer->name,
                    'template_id' => $template?->id,
                    'template_name' => $templateName,
                    'line_items_count' => $lineCount,
                    'grand_total' => (float) $quote->grand_total,
                ],
            ]);
        }

        try {
            $service = new QuoteEmailService;
            $emailRecord = $service->sendQuote($quote, $template, $validated['custom_message'] ?? null);
        } catch (\Exception $e) {
            Log::error('SendQuoteEmailTool: mail send failed', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return Response::error(
                'Email delivery failed: '.$e->getMessage()
            );
        }

        $quote->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "I have sent quote {$quote->reference_number} to {$customer->name} <{$customer->email}>.",
            'url' => route('quotes.show', $quote),
            'email_sent' => [
                'id' => $emailRecord->id,
                'to_email' => $emailRecord->to_email,
                'subject' => $emailRecord->subject,
                'status' => $emailRecord->status,
                'sent_at' => $emailRecord->sent_at?->toIso8601String(),
            ],
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'status' => $quote->status,
                'sent_at' => $quote->sent_at?->toIso8601String(),
            ],
        ]);
    }
}
