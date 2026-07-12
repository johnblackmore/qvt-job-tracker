<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\ProcessedNetlifySubmission;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NetlifyFormService
{
    private string $apiToken;

    private string $siteId;

    private string $notificationEmail;

    private const PER_PAGE = 50;

    public function __construct()
    {
        $this->apiToken = config('services.netlify.api_token');
        $this->siteId = config('services.netlify.site_id');
        $this->notificationEmail = config('services.netlify.notification_email');
    }

    public function sync(): array
    {
        if (blank($this->apiToken) || blank($this->siteId)) {
            Log::error('Netlify sync: API token or site ID not configured.');

            return ['processed' => 0, 'skipped' => 0, 'errors' => 1];
        }

        try {
            $submissions = $this->fetchSubmissions();
        } catch (RequestException $e) {
            Log::error('Netlify sync: failed to fetch submissions', [
                'error' => $e->getMessage(),
                'site_id' => $this->siteId,
            ]);

            return ['processed' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $unprocessed = $this->getUnprocessedSubmissions($submissions);

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($unprocessed as $submission) {
            try {
                $customer = $this->findOrCreateCustomer($submission);
                $enquiry = $this->createEnquiry($customer, $submission);
                $this->sendNotification($enquiry, $customer, $submission);
                $this->markAsProcessed($submission, $customer, $enquiry);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Netlify sync: failed to process submission', [
                    'submission_id' => $submission['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $skipped = count($submissions) - count($unprocessed);

        return compact('processed', 'skipped', 'errors');
    }

    public function preview(): array
    {
        if (blank($this->apiToken) || blank($this->siteId)) {
            return ['unprocessed' => 0, 'message' => 'Netlify sync is not configured.'];
        }

        try {
            $submissions = $this->fetchSubmissions();
        } catch (RequestException $e) {
            return ['unprocessed' => 0, 'message' => 'Failed to fetch submissions: '.$e->getMessage()];
        }

        $unprocessed = $this->getUnprocessedSubmissions($submissions);

        return [
            'unprocessed' => $unprocessed->count(),
            'message' => $unprocessed->isEmpty()
                ? 'No new Netlify form submissions to process.'
                : 'Found '.$unprocessed->count().' unprocessed Netlify form submission(s).',
        ];
    }

    private function fetchSubmissions(int $perPage = self::PER_PAGE): array
    {
        $response = Http::withToken($this->apiToken)
            ->retry(2, 1000)
            ->get("https://api.netlify.com/api/v1/sites/{$this->siteId}/submissions", [
                'per_page' => $perPage,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    private function getUnprocessedSubmissions(array $submissions): Collection
    {
        $processedIds = ProcessedNetlifySubmission::where('site_id', $this->siteId)
            ->pluck('submission_id')
            ->toArray();

        return collect($submissions)->reject(function (array $submission) use ($processedIds) {
            return in_array($submission['id'], $processedIds, true);
        });
    }

    private function findOrCreateCustomer(array $submission): Customer
    {
        $data = $submission['data'] ?? [];
        $email = $submission['email'] ?? $data['email'] ?? null;

        if ($email) {
            $customer = Customer::where('email', $email)->first();
            if ($customer) {
                return $customer;
            }
        }

        $name = $submission['name'] ?? $data['name'] ?? 'Unknown';
        $phone = $data['phone'] ?? $submission['phone'] ?? null;
        $location = $data['location'] ?? null;

        $notes = collect(['vanType', 'services'])
            ->filter(fn ($key) => ! blank($data[$key] ?? null))
            ->map(fn ($key) => ucfirst($key).': '.$data[$key])
            ->implode("\n");

        return Customer::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $location,
            'notes' => $notes ?: null,
        ]);
    }

    private function createEnquiry(Customer $customer, array $submission): Enquiry
    {
        $data = $submission['data'] ?? [];
        $message = $data['message'] ?? $submission['body'] ?? '';

        $parts = collect(['vanType', 'services'])
            ->filter(fn ($key) => ! blank($data[$key] ?? null))
            ->map(fn ($key) => ucfirst($key).': '.$data[$key])
            ->push($message)
            ->filter(fn ($part) => ! blank($part));

        $fullMessage = $parts->implode("\n\n");

        return Enquiry::create([
            'customer_id' => $customer->id,
            'source' => 'web',
            'status' => 'new',
            'subject' => 'Website Enquiry from '.$customer->name,
            'message' => $fullMessage,
        ]);
    }

    private function sendNotification(Enquiry $enquiry, Customer $customer, array $submission): void
    {
        if (blank($this->notificationEmail)) {
            Log::warning('Netlify sync: notification email not configured, skipping notification.');

            return;
        }

        $subject = 'New Website Enquiry — '.$customer->name;

        $bodyHtml = view('emails.netlify-enquiry-notification', [
            'enquiry' => $enquiry,
            'customer' => $customer,
            'submission' => $submission,
        ])->render();

        Mail::html($bodyHtml, function ($message) use ($subject) {
            $message->to($this->notificationEmail)
                ->subject($subject);
        });
    }

    private function markAsProcessed(array $submission, Customer $customer, Enquiry $enquiry): ProcessedNetlifySubmission
    {
        $formId = $submission['form_id'] ?? $submission['formId'] ?? null;

        return ProcessedNetlifySubmission::create([
            'submission_id' => $submission['id'],
            'site_id' => $this->siteId,
            'form_id' => $formId,
            'submission_data' => $submission,
            'customer_id' => $customer->id,
            'enquiry_id' => $enquiry->id,
            'processed_at' => now(),
        ]);
    }
}
