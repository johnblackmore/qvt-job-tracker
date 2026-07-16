<?php

namespace App\Services;

use App\Models\EmailSent;
use Illuminate\Support\Facades\Log;

class EmailWebhookService
{
    public function handleEvent(array $payload): array
    {
        $recordType = $payload['RecordType'] ?? null;

        if (! $recordType) {
            Log::warning('EmailWebhookService: missing RecordType', ['payload' => $payload]);

            return ['action' => 'error', 'reason' => 'Missing RecordType'];
        }

        return match ($recordType) {
            'Delivery' => $this->handleDelivery($payload),
            'Bounce' => $this->handleBounce($payload),
            'Open' => $this->handleOpen($payload),
            'Click' => $this->handleClick($payload),
            'SpamComplaint' => $this->handleSpamComplaint($payload),
            'SubscriptionChange' => $this->handleSubscriptionChange($payload),
            default => ['action' => 'unknown', 'reason' => "Unhandled RecordType: {$recordType}"],
        };
    }

    private function handleDelivery(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        $emailSent->update([
            'status' => 'sent',
            'delivered_at' => $payload['DeliveredAt'] ?? now(),
        ]);

        Log::info('Email delivered', ['email_sent_id' => $emailSent->id, 'to' => $emailSent->to_email]);

        return ['action' => 'delivery_recorded', 'email_sent_id' => $emailSent->id];
    }

    private function handleBounce(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        $emailSent->update([
            'status' => 'failed',
            'bounced_at' => $payload['BouncedAt'] ?? now(),
            'bounce_type' => $payload['Type'] ?? null,
            'error_message' => $payload['Description'] ?? ($payload['Details'] ?? null),
        ]);

        Log::warning('Email bounced', [
            'email_sent_id' => $emailSent->id,
            'to' => $emailSent->to_email,
            'type' => $payload['Type'] ?? 'unknown',
        ]);

        return ['action' => 'bounce_recorded', 'email_sent_id' => $emailSent->id, 'bounce_type' => $payload['Type'] ?? null];
    }

    private function handleOpen(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        if (is_null($emailSent->opened_at)) {
            $emailSent->update(['opened_at' => $payload['ReceivedAt'] ?? now()]);
        }

        return ['action' => 'open_recorded', 'email_sent_id' => $emailSent->id];
    }

    private function handleClick(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        if (is_null($emailSent->clicked_at)) {
            $emailSent->update(['clicked_at' => $payload['ReceivedAt'] ?? now()]);
        }

        return ['action' => 'click_recorded', 'email_sent_id' => $emailSent->id];
    }

    private function handleSpamComplaint(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        $emailSent->update([
            'status' => 'failed',
            'spam_complaint_at' => $payload['BouncedAt'] ?? now(),
        ]);

        Log::warning('Spam complaint received', [
            'email_sent_id' => $emailSent->id,
            'to' => $emailSent->to_email,
        ]);

        return ['action' => 'spam_complaint_recorded', 'email_sent_id' => $emailSent->id];
    }

    private function handleSubscriptionChange(array $payload): array
    {
        $emailSent = $this->findEmailSent($payload['MessageID'] ?? '');

        if (! $emailSent) {
            return $this->notFound(__FUNCTION__);
        }

        if (! empty($payload['SuppressSending'])) {
            $emailSent->update([
                'status' => 'failed',
                'error_message' => 'Recipient unsubscribed: '.($payload['SuppressionReason'] ?? 'unknown'),
            ]);
        }

        Log::info('Subscription change', [
            'email_sent_id' => $emailSent->id,
            'reason' => $payload['SuppressionReason'] ?? null,
        ]);

        return ['action' => 'subscription_change_recorded', 'email_sent_id' => $emailSent->id];
    }

    private function findEmailSent(string $messageId): ?EmailSent
    {
        if (blank($messageId)) {
            return null;
        }

        return EmailSent::where('postmark_message_id', $messageId)->first();
    }

    private function notFound(string $handler): array
    {
        Log::debug('EmailWebhookService: EmailSent not found for '.$handler, func_get_args());

        return ['action' => 'skipped', 'reason' => 'EmailSent record not found for MessageID'];
    }
}
