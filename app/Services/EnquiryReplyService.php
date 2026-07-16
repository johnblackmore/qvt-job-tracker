<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\EnquiryReply;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnquiryReplyService
{
    public function send(Enquiry $enquiry, array $data, ?int $staffUserId = null): EnquiryReply
    {
        $toEmail = $data['to_email'] ?? $enquiry->email ?? $enquiry->customer?->email;

        if (! $toEmail) {
            throw new \InvalidArgumentException('No email address available for this enquiry. Set an email on the enquiry or link to a customer with an email address.');
        }

        $reply = EnquiryReply::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $staffUserId ?? auth()->id(),
            'direction' => 'outbound',
            'subject' => $data['subject'],
            'body' => $data['body'],
            'to_email' => $toEmail,
            'status' => 'draft',
            'message_id' => $this->generateMessageId($enquiry),
            'ai_draft_data' => $data['ai_draft_data'] ?? null,
        ]);

        try {
            $recipientName = $enquiry->customer?->name ?? $enquiry->from_name ?? 'Customer';

            Mail::html(view('emails.enquiry-reply', [
                'subject' => $reply->subject ?? 'Re: '.($enquiry->subject ?? 'Your Enquiry'),
                'body' => $reply->body,
            ])->render(), function ($message) use ($toEmail, $recipientName, $reply, $enquiry) {
                $message->to($toEmail, $recipientName)
                    ->subject($reply->subject ?? 'Re: '.($enquiry->subject ?? 'Your Enquiry'))
                    ->replyTo('enquiries+enquiry-'.$enquiry->id.'@qvt.quantockvantech.com', 'Quantock Van Tech')
                    ->getHeaders()
                    ->addIdHeader('Message-ID', $reply->message_id);
            });

            $reply->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $enquiry->update([
                'status' => 'responded',
                'responded_at' => now(),
                'staff_user_id' => $staffUserId ?? auth()->id(),
            ]);

            EnquiryActivityLog::create([
                'enquiry_id' => $enquiry->id,
                'staff_user_id' => $staffUserId ?? auth()->id(),
                'action' => 'reply_sent',
                'description' => 'Sent reply: '.($reply->subject ?? '(no subject)'),
                'metadata' => ['reply_id' => $reply->id],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send enquiry reply', [
                'enquiry_id' => $enquiry->id,
                'reply_id' => $reply->id,
                'error' => $e->getMessage(),
            ]);

            $reply->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $reply->fresh();
    }

    public function resend(EnquiryReply $originalReply, ?int $staffUserId = null): EnquiryReply
    {
        $enquiry = $originalReply->enquiry;
        $toEmail = $originalReply->to_email ?? $enquiry->email ?? $enquiry->customer?->email;

        if (! $toEmail) {
            throw new \InvalidArgumentException('No email address available for this enquiry.');
        }

        $newReply = EnquiryReply::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $staffUserId ?? auth()->id(),
            'direction' => 'outbound',
            'subject' => $originalReply->subject,
            'body' => $originalReply->body,
            'to_email' => $toEmail,
            'status' => 'draft',
            'message_id' => $this->generateMessageId($enquiry),
            'in_reply_to' => $originalReply->message_id,
        ]);

        try {
            $recipientName = $enquiry->customer?->name ?? $enquiry->from_name ?? 'Customer';

            Mail::html(view('emails.enquiry-reply', [
                'subject' => $newReply->subject ?? 'Re: '.($enquiry->subject ?? 'Your Enquiry'),
                'body' => $newReply->body,
            ])->render(), function ($message) use ($toEmail, $recipientName, $newReply, $enquiry) {
                $message->to($toEmail, $recipientName)
                    ->subject($newReply->subject ?? 'Re: '.($enquiry->subject ?? 'Your Enquiry'))
                    ->replyTo('enquiries+enquiry-'.$enquiry->id.'@qvt.quantockvantech.com', 'Quantock Van Tech')
                    ->getHeaders()
                    ->addIdHeader('Message-ID', $newReply->message_id);
            });

            $newReply->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $enquiry->update([
                'status' => 'responded',
                'responded_at' => now(),
                'staff_user_id' => $staffUserId ?? auth()->id(),
            ]);

            EnquiryActivityLog::create([
                'enquiry_id' => $enquiry->id,
                'staff_user_id' => $staffUserId ?? auth()->id(),
                'action' => 'reply_sent',
                'description' => 'Resent reply: '.($newReply->subject ?? '(no subject)'),
                'metadata' => ['original_reply_id' => $originalReply->id, 'new_reply_id' => $newReply->id],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resend enquiry reply', [
                'enquiry_id' => $enquiry->id,
                'original_reply_id' => $originalReply->id,
                'new_reply_id' => $newReply->id,
                'error' => $e->getMessage(),
            ]);

            $newReply->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $newReply->fresh();
    }

    public function generateMessageId(Enquiry $enquiry): string
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'qvt.quantockvantech.com';

        return sprintf('enquiry.%d.%s@%s', $enquiry->id, now()->format('YmdHis.v'), $domain);
    }
}
