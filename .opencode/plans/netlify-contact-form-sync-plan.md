# Netlify Contact Form Sync Plan

## Overview

Sync Netlify Forms submissions from quantockvantech.com contact form into the QVT Job Tracker system every hour. A scheduled Artisan command will poll the Netlify API, find/create customers, log enquiries, and email staff.

## API Details

### Netlify Submission Endpoints (Read-Only)

| Endpoint | Purpose |
|----------|---------|
| `GET /api/v1/sites/{site_id}/submissions?page=&per_page=` | List all form submissions for the site (newest first) |
| `GET /api/v1/forms/{form_id}/submissions?page=&per_page=` | List submissions for a specific form |
| `GET /api/v1/submissions/{submission_id}` | Get a single submission |

**Auth:** `Authorization: Bearer {NETLIFY_API_TOKEN}` — already set in `.env`

### Submission Response Shape

```json
{
  "id": "string",
  "number": 0,
  "email": "string",
  "name": "string",
  "first_name": "string",
  "last_name": "string",
  "company": "string",
  "summary": "string",
  "body": "string",
  "data": { },
  "created_at": "string",
  "site_url": "string"
}
```

## Form Field Mapping

Based on the example submission, the contact form uses the following `data` keys:

| Form Label | Netlify `data` Key | Maps To |
|------------|-------------------|---------|
| Your name | `name` | Customer `name` |
| Email address | `email` | Customer `email` |
| Phone number | `phone` | Customer `phone` |
| Your location | `location` | Customer `address` |
| Vehicle type | `vehicle-type` | Stored in enquiry notes |
| Service required | `service-required` | Stored in enquiry notes |
| Project details | `project-details` | Enquiry `message` |

**Note:** Netlify uses the form field `name` attribute as the key in `data` (not the label). The keys above are inferred from the submission — the exact keys should be validated against the HTML form on quantockvantech.com.

## Implementation Steps

### Step 1: Environment Configuration

Already set:
```env
NETLIFY_SITE_ID=                    # Added by user
NOTIFICATION_EMAIL=john.blackmore@quantockvantech.com   # Staff notification email
```

Leave mail config as-is — currently SMTP for local testing. Will switch to Postmark in production.

### Step 2: Add Config — `config/services.php`

```php
'netlify' => [
    'api_token' => env('NETLIFY_API_TOKEN'),
    'site_id' => env('NETLIFY_SITE_ID'),
],
```

### Step 3: Create Migration — `processed_netlify_submissions`

Track which submissions have been processed to prevent duplicates.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, auto | PK |
| `submission_id` | string(255) | Netlify submission UUID |
| `site_id` | string(255) | Netlify site ID |
| `form_id` | string(255) | Netlify form ID (nullable) |
| `submission_data` | json | Full submission payload (for audit) |
| `customer_id` | bigint, FK->customers | Created/found customer |
| `enquiry_id` | bigint, FK->enquiries | Created enquiry |
| `processed_at` | timestamp | When processed |

**Unique constraint** on `(submission_id, site_id)` to guarantee idempotency.

Use `php artisan make:migration create_processed_netlify_submissions_table`.

### Step 4: Create Model — `ProcessedNetlifySubmission`

```php
// app/Models/ProcessedNetlifySubmission.php
class ProcessedNetlifySubmission extends Model
{
    protected $fillable = ['submission_id', 'site_id', 'form_id', 'submission_data', 'customer_id', 'enquiry_id', 'processed_at'];

    protected $casts = [
        'submission_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function customer(): BelongsTo { ... }
    public function enquiry(): BelongsTo { ... }
}
```

### Step 5: Create Service — `NetlifyFormService`

```php
// app/Services/NetlifyFormService.php
```

Methods:

| Method | Purpose |
|--------|---------|
| `fetchSubmissions(int $perPage = 50): array` | Calls `GET /sites/{site_id}/submissions` with Bearer token |
| `getUnprocessedSubmissions(array $submissions): Collection` | Filters out already-processed IDs using the `processed_netlify_submissions` table |
| `mapSubmissionToCustomerData(array $submission): array` | Extracts name/email/phone/address from submission `data` |
| `mapSubmissionToEnquiryData(array $submission): array` | Extracts subject/message/notes from submission `data` |
| `findOrCreateCustomer(array $submission): Customer` | Lookup by email; if not found, create with data from `mapSubmissionToCustomerData()` |
| `createEnquiry(Customer $customer, array $submission): Enquiry` | Creates enquiry with `source='web'`, `message` from mapped data, `status='new'` |
| `sendNotification(Enquiry $enquiry, Customer $customer): void` | Sends email to `NOTIFICATION_EMAIL` via `Mail::html()` with submission details |
| `markAsProcessed(string $submissionId, string $siteId, ?string $formId, array $data, Customer $customer, Enquiry $enquiry): ProcessedNetlifySubmission` | Records the processing |
| `sync(): array` | Orchestrates fetch → filter → process → notify → mark; returns summary |

API calls use Laravel's `Http` facade:

```php
$response = Http::withToken(config('services.netlify.api_token'))
    ->get("https://api.netlify.com/api/v1/sites/{$siteId}/submissions", [
        'per_page' => 50,
    ]);
```

### Step 6: Register Scheduled Command — `routes/console.php`

```php
use App\Services\NetlifyFormService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    app(NetlifyFormService::class)->sync();
})->hourly()->name('sync-netlify-submissions');
```

Plus a manual trigger command:

```php
Artisan::command('netlify:sync-submissions', function () {
    $result = app(NetlifyFormService::class)->sync();
    $this->info("Processed: {$result['processed']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");
})->purpose('Sync new Netlify form submissions into enquiries');
```

### Step 7: Notification Email Template

Create `resources/views/emails/netlify-enquiry-notification.blade.php` — a simple HTML email showing:

- Customer name, email, phone
- Subject and message body (project details)
- Link to the enquiry in the staff admin (`route('enquiries.edit', $enquiry)`)
- Link to the customer profile (`route('customers.show', $customer)`)

Styled with the existing email layout conventions (inline styles, QVT branding).

### Step 8: MCP Tool — `SyncNetlifySubmissionsTool` (optional but follows conventions)

Register a new MCP tool for manually triggering the sync from the AI chat:

- **Tool:** `SyncNetlifySubmissionsTool`
- **Pattern:** Preview/confirmed
- **Preview:** Shows count of unprocessed submissions
- **Confirmed:** Runs the sync and returns summary
- Register in `QvtServer::$tools` under a new "Integrations" section

## Error Handling

- Network failures: catch `HttpException`, log, continue (don't mark as processed)
- Customer creation failure: log, skip submission, continue
- Email sending failure: log, but still mark as processed (enquiry already created)
- Each error is logged with submission ID and error message

## Testing

1. **Unit test `NetlifyFormService`** — mock HTTP responses using `Http::fake()`
2. **Test customer matching** — existing customer by email vs new customer creation
3. **Test duplicate prevention** — re-running with same submissions doesn't create duplicates
4. **Test email notification** — assert email is sent to NOTIFICATION_EMAIL
5. **Feature test for Artisan command** — `php artisan netlify:sync-submissions`

## Decisions Made

| Question | Decision |
|----------|----------|
| Mail config | Keep SMTP for local dev, switch to Postmark in production |
| Notification recipient | Single address: `john.blackmore@quantockvantech.com` |
| Site ID | Already added to `.env` by user |
| Form field mapping | To be confirmed by inspecting the HTML form `name` attributes on quantockvantech.com |
