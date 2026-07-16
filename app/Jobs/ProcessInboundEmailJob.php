<?php

namespace App\Jobs;

use App\Services\InboundEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(InboundEmailService $service): void
    {
        try {
            $result = $service->process($this->payload);

            Log::info('Inbound email processed', $result);
        } catch (\Exception $e) {
            Log::error('Failed to process inbound email', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($this->payload),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['inbound-email', 'postmark'];
    }
}
