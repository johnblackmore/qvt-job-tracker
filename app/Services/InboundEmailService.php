<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\EnquiryReply;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InboundEmailService
{
    private ?string $notificationEmail = null;

    public function __construct()
    {
        $this->notificationEmail = config('services.netlify.notification_email');
    }

    public function setNotificationEmail(?string $email): void
    {
        $this->notificationEmail = $email;
    }

    public function process(array $payload): array
    {
        $postmarkMessageId = $payload['MessageID'] ?? null;

        if ($this->alreadyProcessed($postmarkMessageId)) {
            return ['action' => 'skipped', 'reason' => 'Duplicate message ID', 'message' => 'Email already processed.'];
        }

        $fromEmail = $payload['From'] ?? null;
        $fromName = $payload['FromName'] ?? $this->extractFromName($payload);
        $subject = $payload['Subject'] ?? '(no subject)';
        $body = $payload['StrippedTextReply'] ?? $payload['TextBody'] ?? '';
        $mailboxHash = $payload['MailboxHash'] ?? '';
        $inReplyTo = $this->findHeader($payload['Headers'] ?? [], 'In-Reply-To');
        $references = $this->findHeader($payload['Headers'] ?? [], 'References');

        if (blank($fromEmail)) {
            Log::warning('Inbound email missing From address', ['payload' => $payload]);

            return ['action' => 'error', 'reason' => 'Missing sender email', 'message' => 'No sender email address in payload.'];
        }

        $enquiry = $this->matchThread($mailboxHash, $inReplyTo, $references);

        if ($enquiry) {
            return $this->handleThreadReply($enquiry, $fromEmail, $fromName, $subject, $body, $postmarkMessageId, $payload);
        }

        $customer = $this->findOrCreateCustomer($fromEmail, $fromName);
        $enquiry = $this->createEnquiryFromEmail($customer, $fromEmail, $fromName, $subject, $body, $postmarkMessageId, $payload);

        $this->sendStaffNotification($enquiry, $customer, $subject, $body, isNew: true);

        return [
            'action' => 'created',
            'customer_id' => $customer->id,
            'enquiry_id' => $enquiry->id,
            'message' => 'Created new enquiry #'.$enquiry->id.' for '.($customer->name ?? $fromEmail).'.',
        ];
    }

    private function alreadyProcessed(?string $postmarkMessageId): bool
    {
        if (blank($postmarkMessageId)) {
            return false;
        }

        return EnquiryReply::where('postmark_message_id', $postmarkMessageId)->exists();
    }

    private function matchThread(string $mailboxHash, ?string $inReplyTo, ?string $references): ?Enquiry
    {
        if (filled($mailboxHash) && preg_match('/^enquiry-(\d+)$/i', $mailboxHash, $matches)) {
            $enquiry = Enquiry::find((int) $matches[1]);
            if ($enquiry) {
                return $enquiry;
            }
        }

        if (filled($inReplyTo)) {
            $reply = EnquiryReply::where('message_id', $inReplyTo)->first();
            if ($reply && $reply->enquiry) {
                return $reply->enquiry;
            }
        }

        if (filled($references)) {
            $ids = preg_split('/[\s,]+/', $references);
            foreach ($ids as $id) {
                $id = trim($id, '<> ');
                if (filled($id)) {
                    $reply = EnquiryReply::where('message_id', $id)->first();
                    if ($reply && $reply->enquiry) {
                        return $reply->enquiry;
                    }
                }
            }
        }

        return null;
    }

    private function handleThreadReply(
        Enquiry $enquiry,
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?string $postmarkMessageId,
        array $payload
    ): array {
        $this->createInboundReply($enquiry, $fromEmail, $fromName, $subject, $body, $postmarkMessageId);

        $enquiry->update([
            'status' => 'responded',
            'responded_at' => now(),
            'email' => $fromEmail,
            'from_name' => $fromName ?? $enquiry->from_name,
        ]);

        EnquiryActivityLog::create([
            'enquiry_id' => $enquiry->id,
            'action' => 'reply_received',
            'description' => 'Inbound reply received from '.($fromName ?? $fromEmail).': '.str($subject)->limit(100),
            'metadata' => ['from_email' => $fromEmail, 'postmark_message_id' => $postmarkMessageId],
        ]);

        $customer = $enquiry->customer;
        if ($customer) {
            $this->sendStaffNotification($enquiry, $customer, $subject, $body, isNew: false);
        }

        return [
            'action' => 'threaded',
            'enquiry_id' => $enquiry->id,
            'message' => 'Added reply to enquiry #'.$enquiry->id.' from '.($fromName ?? $fromEmail).'.',
        ];
    }

    private function findOrCreateCustomer(string $email, ?string $name): Customer
    {
        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'name' => $name ?? $email,
            'email' => $email,
        ]);
    }

    private function createEnquiryFromEmail(
        Customer $customer,
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?string $postmarkMessageId,
        array $payload
    ): Enquiry {
        $enquiry = Enquiry::create([
            'customer_id' => $customer->id,
            'email' => $fromEmail,
            'from_name' => $fromName,
            'source' => 'email',
            'status' => 'new',
            'subject' => $subject,
            'message' => $body,
        ]);

        $this->createInboundReply($enquiry, $fromEmail, $fromName, $subject, $body, $postmarkMessageId);

        EnquiryActivityLog::create([
            'enquiry_id' => $enquiry->id,
            'action' => 'created_from_email',
            'description' => 'Enquiry created from inbound email: '.str($subject)->limit(100),
            'metadata' => ['from_email' => $fromEmail, 'postmark_message_id' => $postmarkMessageId],
        ]);

        return $enquiry;
    }

    private function createInboundReply(
        Enquiry $enquiry,
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?string $postmarkMessageId
    ): EnquiryReply {
        return EnquiryReply::create([
            'enquiry_id' => $enquiry->id,
            'direction' => 'inbound',
            'subject' => $subject,
            'body' => $body,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to_email' => null,
            'status' => 'received',
            'postmark_message_id' => $postmarkMessageId,
            'sent_at' => now(),
        ]);
    }

    private function sendStaffNotification(Enquiry $enquiry, Customer $customer, string $subject, string $body, bool $isNew): void
    {
        if (blank($this->notificationEmail)) {
            Log::warning('Inbound email: notification email not configured, skipping notification.');

            return;
        }

        $preview = str($body)->limit(300);
        $adminUrl = route('enquiries.show', $enquiry);

        if ($isNew) {
            $emailSubject = 'New Email Enquiry — '.($customer->name ?? 'Unknown');
        } else {
            $emailSubject = 'New Reply on Enquiry #'.$enquiry->id.' — '.($enquiry->subject ?? $subject);
        }

        Mail::html(view('emails.inbound-enquiry-notification', [
            'isNew' => $isNew,
            'enquiry' => $enquiry,
            'customer' => $customer,
            'subject' => $subject,
            'preview' => $preview,
            'adminUrl' => $adminUrl,
        ])->render(), function ($message) use ($emailSubject) {
            $message->to($this->notificationEmail)
                ->subject($emailSubject);
        });
    }

    private function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (($header['Name'] ?? '') === $name) {
                return $header['Value'] ?? null;
            }
        }

        return null;
    }

    private function extractFromName(array $payload): ?string
    {
        if (! empty($payload['FromFull']['Name'])) {
            return $payload['FromFull']['Name'];
        }

        return null;
    }
}
