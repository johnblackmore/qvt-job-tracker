<?php

namespace App\Http\Controllers\Webhook;

use App\Jobs\ProcessInboundEmailJob;
use App\Services\EmailWebhookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request, EmailWebhookService $emailWebhookService)
    {
        $secret = $request->header('X-Postmark-Secret');
        $expected = config('services.postmark.webhook_secret');

        if (blank($expected) || ! hash_equals($expected, (string) $secret)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->all();

        if (! empty($payload['RecordType'])) {
            $emailWebhookService->handleEvent($payload);
        } elseif (! empty($payload['From']) && ! empty($payload['To'])) {
            ProcessInboundEmailJob::dispatch($payload);
        }

        return response('OK', 200);
    }
}
