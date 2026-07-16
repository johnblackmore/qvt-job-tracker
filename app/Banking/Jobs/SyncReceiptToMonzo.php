<?php

namespace App\Banking\Jobs;

use App\Banking\Adapters\MonzoAdapter;
use App\Banking\Services\BankingProviderManager;
use App\Models\Receipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncReceiptToMonzo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public Receipt $receipt;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(Receipt $receipt)
    {
        $this->receipt = $receipt;
    }

    public function handle(BankingProviderManager $providerManager): void
    {
        if (! $this->receipt->bankTransaction) {
            $this->receipt->update(['sync_status' => 'failed']);

            return;
        }

        $bankAccount = $this->receipt->bankTransaction->bankAccount;

        if (! $bankAccount || $bankAccount->provider !== 'monzo') {
            $this->receipt->update(['sync_status' => 'skipped']);

            return;
        }

        try {
            $provider = $providerManager->provider($bankAccount);

            if (! $provider instanceof MonzoAdapter) {
                $this->receipt->update(['sync_status' => 'skipped']);

                return;
            }

            $filePath = $this->receipt->getStoragePath();

            if (! file_exists($filePath)) {
                $this->receipt->update(['sync_status' => 'failed']);

                return;
            }

            $uploadResponse = $provider->uploadAttachment(
                $this->receipt->original_filename,
                $this->receipt->mime_type ?? 'image/jpeg',
                filesize($filePath),
            );

            $uploadUrl = $uploadResponse['upload_url'] ?? null;

            if (! $uploadUrl) {
                throw new \RuntimeException('No upload URL returned from Monzo');
            }

            $fileContent = file_get_contents($filePath);

            $httpClient = new Factory;
            $httpClient->put($uploadUrl, $fileContent)->throw();

            $attachment = $provider->registerAttachment(
                $this->receipt->bankTransaction->provider_transaction_id,
                $uploadResponse['file_url'],
                $this->receipt->mime_type ?? 'image/jpeg',
            );

            $this->receipt->update([
                'monzo_attachment_id' => $attachment['id'] ?? null,
                'sync_status' => 'synced',
            ]);
        } catch (\Exception $e) {
            $this->receipt->update(['sync_status' => 'failed']);

            if ($this->attempts() >= $this->tries) {
                throw $e;
            }

            $this->release($this->backoff);
        }
    }
}
