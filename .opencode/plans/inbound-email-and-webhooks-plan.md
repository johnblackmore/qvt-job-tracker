# Inbound Email Parsing & Outbound Webhook Tracking — Technical Scoping Plan

## Overview

This plan covers two features, built consecutively:

1. **Phase 1 — Inbound Email Parsing** — Receive emails sent to `enquiries@qvt.quantockvantech.com`, parse them via Postmark inbound webhooks, match to existing enquiry threads or create new customer records + enquiries.
2. **Phase 2 — Outbound Email Webhook Tracking** — Track Delivery, Bounce, Open, Click, and SpamComplaint events for emails sent from the app, correlated back to `emails_sent` records.

Both share the same webhook endpoint at `POST /webhooks/postmark` but are logically independent.

---

## Phase 1 — Inbound Email Parsing

### How It Works

```
Customer → Email → enquiries@qvt.quantockvantech.com → Postmark parses → POST JSON → Our webhook
                                                                                ↓
                                                                          200 OK (immediate)
                                                                                ↓
                                                                      ProcessInboundEmailJob
                                                                                ↓
                                                ┌──────────────────────────────┴──────────────┐
                                                ↓                                              ↓
                                          Match thread?                              No thread match
                                                ↓                                              ↓
                                       Create inbound reply                    Find customer by email
                                       on existing Enquiry                     ┌────────┴────────┐
                                                                                ↓                ↓
                                                                            Found           Not found
                                                                                ↓                ↓
                                                                        Create Enquiry    Create Customer
                                                                        linked to          + Create Enquiry
                                                                        customer          + Staff notification
```

### Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Endpoint** | Single shared webhook at `POST /webhooks/postmark` | Inbound and outbound webhooks share the endpoint; route by payload `RecordType` or presence of `From`/`To` fields |
| **Processing** | Async via queued job | Respond 200 immediately, then dispatch `ProcessInboundEmailJob` to the `database` queue — avoids Postmark retries due to slow processing |
| **Inbound domain** | `qvt.quantockvantech.com` | Subdomain avoids risk to the main `quantockvantech.com` email operations; Postmark MX points to `10 inbound.postmarkapp.com` |
| **Threading (primary)** | MailboxHash via `+` addressing | Set `Reply-To: enquiries+enquiry-{id}@qvt.quantockvantech.com` on outbound replies; Postmark parses the hash for automatic routing |
| **Threading (fallback)** | `In-Reply-To` / `References` headers | Match against stored `message_id` on `EnquiryReply` records — works even if the customer strips the `+` address |
| **Customer matching** | Email address lookup | `Customer::where('email', $fromEmail)->first()` — same pattern as `NetlifyFormService` |
| **Deduplication** | `postmark_message_id` on `EnquiryReply` | Inbound replies store Postmark's MessageID; skip if already processed |
| **Staff notification** | Email sent for both new enquiries and thread replies | Staff get notified of all inbound email activity, with a link to the staff admin area |
| **Security** | Custom HTTP header shared secret | Postmark sends `X-Postmark-Secret` on every webhook call; verified in controller |
| **Queue** | `database` driver with `php artisan queue:work` | Already configured in `.env` (`QUEUE_CONNECTION=database`); worker needs to run in a terminal/supervisor |

### Data Model Changes

#### Migration 1: Add `from_name` to enquiries

```php
Schema::table('enquiries', function (Blueprint $table) {
    $table->string('from_name')->nullable()->after('email');
});
```

#### Migration 2: Add webhook tracking columns to `emails_sent`

```php
Schema::table('emails_sent', function (Blueprint $table) {
    $table->json('metadata')->nullable()->after('error_message');
    $table->timestamp('opened_at')->nullable()->after('metadata');
    $table->timestamp('clicked_at')->nullable()->after('opened_at');
    $table->timestamp('bounced_at')->nullable()->after('clicked_at');
    $table->string('bounce_type')->nullable()->after('bounced_at');
    $table->timestamp('spam_complaint_at')->nullable()->after('bounce_type');
    $table->timestamp('delivered_at')->nullable()->after('spam_complaint_at');
});
```

### New Files

| File | Purpose |
|------|---------|
| `app/Services/InboundEmailService.php` | Core logic: parse payload, thread match, find/create customer, create enquiry, create reply, send notification |
| `app/Jobs/ProcessInboundEmailJob.php` | Queued job that calls `InboundEmailService` |
| `app/Http/Controllers/Webhook/PostmarkWebhookController.php` | Single endpoint that routes inbound vs outbound payloads |
| `app/Mcp/Tools/InboundEmail/ListInboundRepliesTool.php` | Read-only MCP tool to list inbound email replies |
| `app/Mcp/Tools/InboundEmail/GetInboundReplyTool.php` | Read-only MCP tool to view a specific inbound reply |
| `tests/Feature/Webhooks/PostmarkWebhookTest.php` | Feature tests for the webhook endpoint |
| `tests/Feature/Services/InboundEmailServiceTest.php` | Tests for the inbound email service |
| `tests/Unit/Jobs/ProcessInboundEmailJobTest.php` | Tests for the queued job |
| `resources/views/emails/inbound-enquiry-notification.blade.php` | Staff notification email for new inbound email activity |

### Key Implementation Details

#### 1. Reply-To Header on Outbound Replies

In `EnquiryReplyService::send()`, add the `Reply-To` header with the threaded address:

```php
$message->replyTo('enquiries+enquiry-'.$enquiry->id.'@qvt.quantockvantech.com', 'Quantock Van Tech');
```

When the customer hits Reply, their email goes to `enquiries+enquiry-123@qvt.quantockvantech.com`. Postmark parses `MailboxHash` as `enquiry-123`, and we immediately know which enquiry this is.

#### 2. Threading Logic (InboundEmailService)

Priority order:
1. **MailboxHash parse** — Extract `enquiry-{id}` from `MailboxHash` → direct `Enquiry::find($id)`
2. **In-Reply-To header** — Find `In-Reply-To` in `Headers` array → match against `EnquiryReply::where('message_id', $inReplyTo)->first()?->enquiry`
3. **References header** — Split `References` into individual IDs → search for any matching `message_id` on replies
4. **No match** — fall through to customer+enquiry creation

#### 3. InboundEmailService::process() Flow

```
process(array $payload):
  1. Check dedup — return early if postmark_message_id already processed
  2. Extract: fromEmail, fromName, subject, body (StrippedTextReply ?? TextBody)
  3. Try thread match → $enquiry
  4. If matched:
     a. Create inbound EnquiryReply (direction='inbound', status='received')
     b. Update enquiry status to 'responded' (customer replied)
     c. Log activity
     d. Send staff notification with link to enquiry
  5. If no match:
     a. Find or create Customer by email
     b. Create new Enquiry (source='email')
     c. Create inbound EnquiryReply
     d. Log activity
     e. Send staff notification with link to enquiry
  6. Return result summary
```

#### 4. Staff Notification Details

Send notification to `config('services.netlify.notification_email')` (same `NOTIFICATION_EMAIL` env var used by Netlify sync).

**For new enquiries:**
- Subject: `New Email Enquiry — {customer name}`
- Body includes: sender name, email, subject, message preview, and a link to the enquiry in the admin panel

**For thread replies:**
- Subject: `New Reply on Enquiry #{id} — {subject}`
- Body includes: sender name, reply preview, and a link to the existing enquiry thread

#### 5. Webhook Controller

```php
class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = $request->header('X-Postmark-Secret');
        if (! hash_equals(config('services.postmark.webhook_secret'), $secret)) {
            return response('Unauthorized', 401);
        }

        // Respond 200 immediately — process asynchronously
        if ($request->has('RecordType')) {
            // Outbound webhook event — handled in Phase 2
            EmailWebhookService::dispatchEvent($request->all());
        } else {
            // Inbound email
            ProcessInboundEmailJob::dispatch($request->all());
        }

        return response('OK', 200);
    }
}
```

#### 6. Route

```php
Route::post('/webhooks/postmark', [PostmarkWebhookController::class, '__invoke'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
```

Added to `routes/web.php` (or a dedicated `routes/webhooks.php`). CSRF exempt, throttled at 60 requests per minute.

#### 7. Inbound Domain Configuration

| Setting | Value |
|---------|-------|
| **Inbound domain** | `qvt.quantockvantech.com` |
| **MX record** | `10 inbound.postmarkapp.com` |
| **Catch-all address** | `enquiries@qvt.quantockvantech.com` |
| **Webhook URL** | `https://your-app.com/webhooks/postmark` |

The MX record points the subdomain's email handling to Postmark. All email sent to `*@qvt.quantockvantech.com` is processed by Postmark's inbound stream.

#### 8. Postmark Setup Checklist

- [ ] Add `qvt.quantockvantech.com` as inbound domain in Postmark server settings
- [ ] Configure MX record `10 inbound.postmarkapp.com` for `qvt.quantockvantech.com` with domain registrar
- [ ] Set inbound webhook URL to `https://your-app.com/webhooks/postmark`
- [ ] Configure outbound webhook (Phase 2)
- [ ] Set custom HTTP header `X-Postmark-Secret` on Postmark webhook config
- [ ] Set `POSTMARK_WEBHOOK_SECRET` in `.env`
- [ ] Add to `config/services.php`:
  ```php
  'postmark' => [
      'api_key' => env('POSTMARK_API_KEY'),
      'webhook_secret' => env('POSTMARK_WEBHOOK_SECRET'),
  ],
  ```
- [ ] Outbound replies use `Reply-To: enquiries+enquiry-{id}@qvt.quantockvantech.com`

### MCP Tool Changes (Phase 1)

Add to `QvtServer` under "Enquiry tools":

| Tool | Class | Description |
|------|-------|-------------|
| `ListInboundRepliesTool` | `App\Mcp\Tools\ListInboundRepliesTool` | Read-only. Lists inbound-direction `EnquiryReply` records with date/source filters. Each item includes `url` to the staff admin view. |
| `GetInboundReplyTool` | `App\Mcp\Tools\GetInboundReplyTool` | Read-only. View a single inbound reply with full details, the enquiry it belongs to, and a `url` field. |

---

## Phase 2 — Outbound Email Webhook Tracking

### How It Works

```
Staff sends email → Postmark delivers → Recipient server responds
                                              ↓
                                    Postmark fires webhook event
                                    (Delivery / Bounce / Open / Click / SpamComplaint)
                                              ↓
                                    POST JSON → Our webhook endpoint
                                              ↓
                                        200 OK (immediate)
                                              ↓
                                    EmailWebhookService
                                              ↓
                                    Find EmailSent by MessageID
                                              ↓
                                    Update status / timestamps
```

### Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Correlation** | `postmark_message_id` on `EmailSent` table | Postmark includes the MessageID in all webhook payloads — simple lookup |
| **Event storage** | Timestamp columns on `emails_sent` | Minimal schema change; one row per sent email with event timestamps. Sufficient since Postmark's `PostFirstOpenOnly` suppresses duplicate events |
| **Bounce handling** | Update status to `failed` + record `bounce_type` | Staff reviews bounced emails in the admin UI |
| **Open/Click tracking** | First open/click only (set `PostFirstOpenOnly: true`) | Reliable engagement signal without noise |

### New Files

| File | Purpose |
|------|---------|
| `app/Services/EmailWebhookService.php` | Process outbound webhook events: Delivery, Bounce, Open, Click, SpamComplaint, SubscriptionChange |
| `tests/Feature/Services/EmailWebhookServiceTest.php` | Tests for the webhook service |

### Key Implementation Details

#### 1. Event Routing

The `PostmarkWebhookController` routes by checking `$request->input('RecordType')`:

```php
match ($request->input('RecordType')) {
    'Delivery' => $service->handleDelivery($payload),
    'Bounce' => $service->handleBounce($payload),
    'Open' => $service->handleOpen($payload),
    'Click' => $service->handleClick($payload),
    'SpamComplaint' => $service->handleSpamComplaint($payload),
    'SubscriptionChange' => $service->handleSubscriptionChange($payload),
    default => null, // Not an outbound event
};
```

Payload routing logic:
- Has `RecordType` → outbound webhook event → `EmailWebhookService`
- No `RecordType`, has `From` + `To` → inbound email → `ProcessInboundEmailJob`

#### 2. Event Handlers

Each handler extracts `MessageID`, looks up `EmailSent::where('postmark_message_id', $messageId)->first()`, and updates timestamps.

**Delivery:**
```php
$emailSent->update([
    'status' => 'sent',
    'delivered_at' => $payload['DeliveredAt'],
]);
```

**Bounce:**
```php
$emailSent->update([
    'status' => 'failed',
    'bounced_at' => $payload['BouncedAt'],
    'bounce_type' => $payload['Type'],
    'error_message' => $payload['Description'] ?? null,
]);
```

**Open:**
```php
if (is_null($emailSent->opened_at)) {
    $emailSent->update(['opened_at' => $payload['ReceivedAt']]);
}
```

**Click:**
```php
if (is_null($emailSent->clicked_at)) {
    $emailSent->update(['clicked_at' => $payload['ReceivedAt']]);
}
```

**SpamComplaint:**
```php
$emailSent->update([
    'status' => 'failed',
    'spam_complaint_at' => $payload['BouncedAt'],
]);
```

#### 3. Postmark Webhook Configuration

Create webhook via API or dashboard:
- **MessageStream:** `outbound`
- **URL:** `https://your-app.com/webhooks/postmark`
- **Custom header:** `X-Postmark-Secret`
- **Triggers:**
  - Delivery: enabled
  - Bounce: enabled, IncludeContent: true
  - Open: enabled, PostFirstOpenOnly: true
  - Click: enabled
  - SpamComplaint: enabled, IncludeContent: true
  - SubscriptionChange: enabled

### New MCP Tools for Phase 2

Add to `QvtServer` under "Communication tools":

| Tool | Class | Description |
|------|-------|-------------|
| `ListEmailSentTool` | `App\Mcp\Tools\ListEmailSentTool` | Read-only. List sent emails with filtering by status, customer, date. Each item includes `url` to admin view. |
| `GetEmailSentTool` | `App\Mcp\Tools\GetEmailSentTool` | Read-only. View a single sent email with all webhook event timestamps and correlated data. |

---

## Queue Worker Configuration

### Current State

The `.env` already has `QUEUE_CONNECTION=database`, and the `jobs` table migration already exists. This means Laravel will store queued jobs in the `jobs` database table. No additional migration or config change is needed.

### How to Run the Worker

**Development (terminal):**
```bash
php artisan queue:work --queue=default --tries=3 --delay=10
```

This processes jobs from the `default` queue, retries up to 3 times, and waits 10 seconds between retries. Run this alongside `composer run dev` (which runs `vite`) in a separate terminal tab.

**Using `&` background (quick dev approach):**
```bash
php artisan queue:work --queue=default --tries=3 --delay=10 &
```

### Production (Supervisor)

For production, create a Supervisor config at `/etc/supervisor/conf.d/qvt-queue-worker.conf`:

```ini
[program:qvt-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### Composer Dev Script (Optional)

Add a convenience script to `composer.json` scripts section:

```json
"dev": [
    "composer run dev:queue &",
    "composer run dev:vite"
],
"dev:queue": "@php artisan queue:work --queue=default --tries=3 --delay=10",
"dev:vite": "vite"
```

Or use Laravel's built-in `composer run dev` which already runs `php artisan serve` + `vite`. The queue worker would need to be started separately.

---

## Threading Strategy — Detailed

### Outbound (we send to customer)

We already set a custom `Message-ID` header:
```
enquiry.{enquiryId}.{timestamp}@qvt.quantockvantech.com
```

We need to **add** on every outbound reply:
```
Reply-To: enquiries+enquiry-{enquiryId}@qvt.quantockvantech.com
```

### Inbound (customer replies to us)

When the customer replies, Postmark processes the email and POSTs to our webhook:

1. **MailboxHash routing (primary)** — if the customer replies to the `Reply-To` address, Postmark parses `enquiry-123` from the mailbox hash. We extract the enquiry ID directly.

2. **In-Reply-To fallback** — if the customer alters the `To` address (removing the `+` hash), we fall back to `In-Reply-To` header matching. We search `EnquiryReply::where('message_id', $inReplyTo)->first()` to find the parent reply and its enquiry.

3. **References fallback** — if `In-Reply-To` is empty but `References` contains known message IDs, match those.

### Customer Created by Inbound Email

When an email arrives from an unknown sender:

```
Customer fields: name (from FromName), email (from From)
Enquiry fields: from_name (FromName), email (From), subject (Subject), message (StrippedTextReply), source = 'email', status = 'new'
```

---

## Staff Notification Email

### For New Enquiries (no thread match)

Sent when a completely new email arrives from an unknown or known customer.

Email includes:
- Sender name and email
- Subject line
- Message preview (first ~300 chars)
- **Link:** `<a href="{{ route('enquiries.show', $enquiry) }}">View Enquiry #{{ $enquiry->id }} in Staff Admin</a>`

### For Thread Replies (matched to existing enquiry)

Sent when a reply is matched to an existing enquiry thread.

Email includes:
- Sender name and email
- Original enquiry subject
- Reply preview
- **Link:** `<a href="{{ route('enquiries.show', $enquiry) }}">View Reply in Staff Admin</a>`

### Both use the same notification channel as Netlify sync

Send via `Mail::html()` using a dedicated blade view. Recipient is `config('services.netlify.notification_email')`.

---

## Postmark Setup (Both Phases)

### Inbound Domain MX Setup

```
Type: MX
Host: qvt
Value: 10 inbound.postmarkapp.com
TTL: 3600
```

### Inbound Stream Configuration

- **Inbound domain:** `qvt.quantockvantech.com`
- **Catch-all:** `enquiries@qvt.quantockvantech.com` (or let the wildcard catch `*@qvt.quantockvantech.com`)
- **Webhook URL:** `https://your-app.com/webhooks/postmark`

### Outbound Webhook Configuration

Create via Postmark API:
```bash
curl "https://api.postmarkapp.com/webhooks" \
  -X POST \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Postmark-Server-Token: $POSTMARK_SERVER_TOKEN" \
  -d '{
    "Url": "https://your-app.com/webhooks/postmark",
    "MessageStream": "outbound",
    "HttpHeaders": [
      {"Name": "X-Postmark-Secret", "Value": "'"$POSTMARK_WEBHOOK_SECRET"'"}
    ],
    "Triggers": {
      "Open": {"Enabled": true, "PostFirstOpenOnly": true},
      "Click": {"Enabled": true},
      "Delivery": {"Enabled": true},
      "Bounce": {"Enabled": true, "IncludeContent": true},
      "SpamComplaint": {"Enabled": true, "IncludeContent": true},
      "SubscriptionChange": {"Enabled": true}
    }
  }'
```

### Security

Generate secret:
```bash
php -r 'echo bin2hex(random_bytes(32));'
```

Add to `.env`:
```
POSTMARK_WEBHOOK_SECRET=abc123...
```

Add to `config/services.php`:
```php
'postmark' => [
    'api_key' => env('POSTMARK_API_KEY'),
    'webhook_secret' => env('POSTMARK_WEBHOOK_SECRET'),
],
```

Verify in controller:
```php
$received = $request->header('X-Postmark-Secret');
$expected = config('services.postmark.webhook_secret');

if (! hash_equals((string) $expected, (string) $received)) {
    return response('Unauthorized', 401);
}
```

---

## Testing Strategy

### Inbound Email Service Tests

| Test | What it covers |
|------|---------------|
| `test_it_processes_a_new_inbound_email` | Full flow: unknown sender → creates customer + enquiry + reply |
| `test_it_threads_a_reply_via_mailbox_hash` | MailboxHash `enquiry-{id}` → finds enquiry, creates inbound reply |
| `test_it_threads_a_reply_via_in_reply_to_header` | Matches `In-Reply-To` against stored `message_id` |
| `test_it_threads_a_reply_via_references_header` | Matches one of several `References` |
| `test_it_skips_duplicate_postmark_message_ids` | Same `postmark_message_id` already exists → no-op |
| `test_it_creates_inbound_reply_with_correct_direction` | `direction = 'inbound'`, `status = 'received'` |
| `test_it_finds_existing_customer_by_email` | Customer exists with same email → links new enquiry to them |
| `test_it_creates_customer_when_no_match` | Unknown email → creates new customer |
| `test_it_logs_activity_for_new_enquiry` | Check `EnquiryActivityLog` is created |
| `test_it_logs_activity_for_threaded_reply` | Check activity log for threaded reply |
| `test_it_uses_stripped_text_reply_when_available` | Prefers `StrippedTextReply` over `TextBody` |
| `test_it_sends_staff_notification_for_new_enquiry` | Notification email sent with admin link |
| `test_it_sends_staff_notification_for_thread_reply` | Notification email sent with admin link |

### Webhook Controller Tests

| Test | What it covers |
|------|---------------|
| `test_it_returns_200_for_valid_inbound_payload` | Happy path |
| `test_it_rejects_requests_without_valid_secret` | Returns 401 |
| `test_it_dispatches_job_for_inbound_payload` | Job dispatched to queue |
| `test_it_routes_outbound_events_to_email_webhook_service` | Outbound events processed |
| `test_it_handles_malformed_payload_gracefully` | Returns 200 (no crash) |

### Email Webhook Service Tests (Phase 2)

| Test | What it covers |
|------|---------------|
| `test_it_records_delivery` | Updates `delivered_at` and `status` |
| `test_it_records_hard_bounce` | Updates `bounced_at`, `bounce_type`, `status = failed` |
| `test_it_records_soft_bounce` | Updates bounce fields |
| `test_it_records_spam_complaint` | Updates `spam_complaint_at`, `status = failed` |
| `test_it_records_first_open_only` | Subsequent opens don't overwrite `opened_at` |
| `test_it_records_first_click_only` | Subsequent clicks don't overwrite `clicked_at` |
| `test_it_handles_unknown_message_id` | Logs warning, no crash |
| `test_it_handles_subscription_change` | Updates suppression status |

### MCP Tool Tests

| Test | What it covers |
|------|---------------|
| `test_list_inbound_replies_requires_admin` | Unauthenticated returns 401 |
| `test_list_inbound_replies_returns_paginated_results` | Returns reply data with URLs |
| `test_get_inbound_reply_returns_enquiry_reply_data` | Returns reply with URL |
| `test_list_email_sent_returns_paginated_results` | Phase 2: returns email data with URLs |
| `test_get_email_sent_returns_webhook_timestamps` | Phase 2: returns all event timestamps |

---

## Implementation Order

### Phase 1 — Inbound Email (~2-3 days)

1. Create migration: add `from_name` to enquiries, webhook tracking columns to `emails_sent`
2. Run `php artisan migrate`
3. Add `POSTMARK_WEBHOOK_SECRET` to `.env` and `config/services.php`
4. Create `InboundEmailService` with thread matching, customer matching, reply creation, staff notification
5. Create `ProcessInboundEmailJob` with `$payload` data, `database` queue connection, 3 retries
6. Create `PostmarkWebhookController` with secret verification and payload routing
7. Register webhook route (CSRF exempt, throttled)
8. Update `EnquiryReplyService::send()` to set `Reply-To: enquiries+enquiry-{id}@qvt.quantockvantech.com`
9. Create staff notification email template view
10. Create MCP tools: `ListInboundRepliesTool`, `GetInboundReplyTool`
11. Register MCP tools in `QvtServer`
12. Write tests

### Phase 2 — Outbound Webhook Tracking (~1-2 days)

1. Create `EmailWebhookService` with all event handlers
2. Wire into `PostmarkWebhookController` (route by `RecordType`)
3. Create MCP tools: `ListEmailSentTool`, `GetEmailSentTool`
4. Register MCP tools in `QvtServer`
5. Write tests

### After Both Phases

- Run full test suite: `php artisan test --compact`
- Run lint: `vendor/bin/pint --format agent`
- Configure Postmark inbound domain (`qvt.quantockvantech.com`) with MX record
- Configure Postmark outbound webhook with event triggers
- Start queue worker: `php artisan queue:work --queue=default --tries=3 --delay=10`
- Verify end-to-end with a test inbound email

---

## Resolved Questions

| Question | Decision |
|----------|----------|
| **Inbound domain** | `qvt.quantockvantech.com` — subdomain to isolate from main domain email |
| **Queue worker** | Already configured (`QUEUE_CONNECTION=database`); run `php artisan queue:work` manually in dev, Supervisor in prod |
| **Staff notification scope** | Notify on BOTH new enquiries AND thread replies, with admin link in every notification |
| **Build order** | Both features, consecutively — Phase 1 (inbound parsing) first, then Phase 2 (outbound webhooks) |
| **Single endpoint or separate?** | Single endpoint — route by payload structure |
| **Processing mode** | Async via queued job (respond 200, then process) |
| **Event storage** | Columns on `emails_sent` — simpler, sufficient for first-event tracking |
| **from_name on enquiries** | Yes — captures the sender's name for new enquiries without a customer record |
| **Threading fallback** | `In-Reply-To` / `References` header matching when `+` address is stripped |
| **Hard bounce → flag customer?** | Not in MVP — record on `EmailSent` for manual staff review |
| **MCP tools for inbound email** | Yes — read-only list/get tools for visibility |
| **MCP tools for outbound tracking** | Yes — read-only list/get tools for visibility |

---

## Open Questions

None. All previously open questions have been resolved.

---

## Postmark Configuration Checklist

### 1. Inbound Domain — MX Record

Add this DNS record with your domain registrar for `qvt.quantockvantech.com`:

| Type | Host | Value |
|------|------|-------|
| MX | `qvt` | `10 inbound.postmarkapp.com` |

### 2. Postmark Server — Inbound Stream

- Add `qvt.quantockvantech.com` as an inbound domain in your Postmark server settings
- Set the inbound webhook URL to `https://your-app.com/webhooks/postmark`
- The catch-all receives emails at `enquiries@qvt.quantockvantech.com`

### 3. Postmark Server — Outbound Webhook

Create a webhook for the `outbound` message stream pointing to the same URL:

```
https://your-app.com/webhooks/postmark
```

Enable triggers: Delivery, Bounce, Open (PostFirstOpenOnly), Click, SpamComplaint, SubscriptionChange.

### 4. Webhook Security Header

On both webhooks (inbound + outbound), add a custom HTTP header:

```
X-Postmark-Secret: <your-generated-secret>
```

Generate the secret with `php -r 'echo bin2hex(random_bytes(32));'` and set it in `.env`:

```
POSTMARK_WEBHOOK_SECRET=<your-generated-secret>
```

### 5. Queue Worker

In a terminal, run:

```bash
php artisan queue:work --queue=default --tries=3 --delay=10
```

This processes inbound emails asynchronously from the webhook. For production, use Supervisor (see config above).

### 6. Verification

- Send a test email to `enquiries@qvt.quantockvantech.com` and check the webhook fires
- Send a test outbound email from the app and check the webhook events arrive
- Check `php artisan queue:status` or view the `jobs` table to verify queue processing
