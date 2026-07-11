<?php

namespace App\Services;

use App\Models\EmailSent;
use App\Models\EmailTemplate;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteEmailService
{
    public function sendQuote(Quote $quote, ?EmailTemplate $template = null, ?string $customMessage = null): EmailSent
    {
        $quote->load(['customer', 'lineItems']);
        $customer = $quote->customer;

        if (! $customer || ! $customer->email) {
            throw new \InvalidArgumentException('Customer has no email address.');
        }

        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        $pdf->setPaper('a4');
        $pdfContent = $pdf->output();
        $pdfFilename = sprintf('QVT-Quote-%s.pdf', $quote->reference_number);

        $emailRecord = EmailSent::create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'template_id' => $template?->id,
            'to_email' => $customer->email,
            'subject' => $this->buildSubject($quote, $template),
            'body_html' => $this->buildBody($quote, $template, $customMessage),
            'status' => 'pending',
        ]);

        try {
            Mail::html($emailRecord->body_html, function ($message) use ($customer, $emailRecord, $pdfContent, $pdfFilename) {
                $message->to($customer->email, $customer->name)
                    ->subject($emailRecord->subject)
                    ->attachData($pdfContent, $pdfFilename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            $emailRecord->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if ($quote->status === 'draft') {
                $quote->update(['status' => 'sent', 'sent_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send quote email', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            $emailRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $emailRecord;
    }

    private function buildSubject(Quote $quote, ?EmailTemplate $template): string
    {
        if ($template) {
            $rendered = $template->render([
                'quote_reference' => $quote->reference_number,
                'customer_name' => $quote->customer->name,
                'valid_until' => $quote->valid_until?->format('d F Y') ?? '',
            ]);

            return $rendered['subject'];
        }

        return sprintf('Your Quote from Quantock Van Tech — %s', $quote->reference_number);
    }

    private function buildBody(Quote $quote, ?EmailTemplate $template, ?string $customMessage): string
    {
        $data = [
            'quote_reference' => $quote->reference_number,
            'customer_name' => $quote->customer->name,
            'valid_until' => $quote->valid_until?->format('d F Y') ?? '',
            'grand_total' => '£'.number_format($quote->grand_total, 2),
            'custom_message' => $customMessage ?? '',
        ];

        if ($template) {
            $rendered = $template->render($data);

            return $rendered['html'];
        }

        $lines = [];
        foreach ($quote->lineItems as $item) {
            $lines[] = sprintf(
                '<tr><td>%s</td><td>%d</td><td>£%s</td><td>£%s</td></tr>',
                e($item->description),
                $item->quantity,
                number_format($item->unit_retail_price, 2),
                number_format($item->line_total_retail, 2)
            );
        }

        return view('emails.quote-default', [
            'quote' => $quote,
            'linesHtml' => implode("\n", $lines),
            'customMessage' => $customMessage,
        ])->render();
    }
}
