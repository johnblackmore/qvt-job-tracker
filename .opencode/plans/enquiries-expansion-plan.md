# Enquiries Expansion & AI Draft Assistant — Implementation Plan

## Overview

Transform the Enquiries section from a basic log into a full communications hub with quote linking, bidirectional conversation threading, and AI-assisted draft responses.

**Phases:**
1. Enquiry-Quote Link
2. Reply System with Threading (outbound + future inbound)
3. Expanded Enquiry Functionality (internal notes, activity log, enhanced filters, staff assignment)
4. AI Draft Assistant (never sends directly)
5. MCP Tool Integration

---

## Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Reply Storage** | `enquiry_replies` table with direction flag | Enables bidirectional threading from day one — staff outbound and future customer inbound replies use the same table |
| **Email Delivery** | Postmark via existing mail config | Already configured, used for quote emails |
| **Threading** | RFC 2822 `Message-ID` / `In-Reply-To` headers | Standard email threading; Postmark inbound webhooks include `In-Reply-To` for automatic matching |
| **AI Provider** | Existing `AiModelConfig` settings + Prism | Reuses the configurable provider/model UI already built |
| **AI Draft Pattern** | Manual trigger only | Staff clicks button to generate draft, reviews and edits, then sends manually — AI never calls Postmark |
| **Quote-Enquiry Link** | `enquiry_id` FK on `quotes` table | Simple, indexed, supports one-to-many (one enquiry → multiple quotes) |
| **Activity Log** | `enquiry_activity_logs` table | Immutable audit trail of status changes, replies, and quote creations |

---

## Phase 1 — Enquiry-Quote Link

### Migration

```php
// database/migrations/2026_07_XX_XXXXXX_add_enquiry_id_to_quotes_table.php
Schema::table('quotes', function (Blueprint $table) {
    $table->foreignId('enquiry_id')
        ->nullable()
        ->constrained('enquiries')
        ->onDelete('set null')
        ->after('customer_id');

    $table->index('enquiry_id');
});
```

### Model Changes

**`App\Models\Enquiry`** — add:
```php
public function quotes(): HasMany
{
    return $this->hasMany(Quote::class);
}
```

**`App\Models\Quote`** — add:
```php
public function enquiry(): BelongsTo
{
    return $this->belongsTo(Enquiry::class);
}
```

Update `$fillable` to include `'enquiry_id'`.

### UI Changes

- **`EnquiryShow` (new):** Show linked quotes card — lists reference number, status, grand total, created date. "Create Quote" button → `QuoteBuilder` with `?enquiryId=` param.
- **`QuoteBuilder`:** Accept `?enquiryId=` query param. If set, pre-fill `customer_id` from `$enquiry->customer_id`. After save, set `enquiry_id` on the quote.
- **Customer Show:** Already shows enquiries. No change needed — quotes already visible on customer page.

### MCP Tool

**`CreateQuoteFromEnquiryTool`** — write, preview/confirmed:

| Param | Type | Description |
|-------|------|-------------|
| `enquiry_id` | int (required) | The enquiry to create a quote from |
| `status` | string (default: `draft`) | Initial quote status |

Logic: Loads enquiry → creates quote with `enquiry_id` and `customer_id` from enquiry → returns URL to quote builder → logs activity.

---

## Phase 2 — Reply System with Bidirectional Threading

### Migration: `enquiry_replies`

```php
Schema::create('enquiry_replies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('enquiry_id')->constrained()->onDelete('cascade');
    $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('set null');
    // Outbound = staff sent, Inbound = customer/email reply
    $table->string('direction')->default('outbound');  // outbound|inbound
    $table->string('subject')->nullable();
    $table->text('body');
    $table->string('to_email')->nullable();   // recipient (outbound) or sender (inbound)
    $table->string('from_email')->nullable(); // for inbound replies
    $table->string('from_name')->nullable();
    $table->string('status')->default('draft');  // draft|sent|failed|received

    // Email threading — essential for Postmark inbound matching
    $table->string('message_id')->nullable()->unique();        // RFC 2822 Message-ID (we generate on outbound)
    $table->string('in_reply_to')->nullable();                 // RFC 2822 In-Reply-To (matched on inbound)
    $table->string('postmark_message_id')->nullable();         // Postmark's own message ID for tracking

    $table->json('ai_draft_data')->nullable();                 // AI-generated draft stored alongside the reply
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();
});
```

### Threading Flow

**Outbound (staff sends):**
1. Staff composes reply in `EnquiryShow` → clicks Send
2. `EnquiryReplyService::send()` generates RFC 2822 `Message-ID`: `<enquiry.{id}.reply.{replyId}@{domain}>`
3. Sends via Postmark with `Message-ID` header
4. Stores `message_id`, `postmark_message_id` (from Postmark response), `status: sent`, `sent_at: now()`
5. Auto-updates enquiry: `status: responded`, `responded_at: now()`

**Inbound (future — customer replies):**
1. Postmark inbound webhook POSTs to a new route (e.g. `/webhooks/postmark/inbound`)
2. Webhook payload includes `In-Reply-To` header matching our `Message-ID`
3. Lookup `enquiry_reply` by `message_id` matching `in_reply_to`
4. Create new reply with `direction: inbound`, linked to same `enquiry_id`
5. Auto-updates enquiry: `status: in_progress` (customer has replied, needs attention)

### Model

**`App\Models\EnquiryReply`:**
```php
class EnquiryReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id', 'staff_user_id', 'direction', 'subject', 'body',
        'to_email', 'from_email', 'from_name', 'status',
        'message_id', 'in_reply_to', 'postmark_message_id',
        'ai_draft_data', 'sent_at',
    ];

    protected $casts = [
        'ai_draft_data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function enquiry(): BelongsTo { return $this->belongsTo(Enquiry::class); }
    public function staff(): BelongsTo { return $this->belongsTo(User::class, 'staff_user_id'); }
}
```

**`App\Models\Enquiry`** — add:
```php
public function replies(): HasMany
{
    return $this->hasMany(EnquiryReply::class)->orderBy('created_at');
}

public function latestReply(): HasOne
{
    return $this->hasOne(EnquiryReply::class)->latestOfMany();
}
```

### Service: `EnquiryReplyService`

```php
class EnquiryReplyService
{
    public function send(Enquiry $enquiry, array $data): EnquiryReply;
    public function generateMessageId(Enquiry $enquiry, EnquiryReply $reply): string;
    public function handleInboundEmail(array $payload): EnquiryReply;  // future
}
```

`send()` method:
1. Validates customer has email (from linked customer or `enquiry.email`)
2. Creates reply with `status: draft`, generates `message_id`
3. Sends via Postmark using `Mail::html()` — reuses existing mail config
4. On success: updates reply `status: sent`, `sent_at`, `postmark_message_id`
5. On failure: updates reply `status: failed`, `error_message`
6. Auto-updates enquiry `status: responded`, `responded_at`

### Livewire: `EnquiryShow`

New full-page component at `/enquiries/{id}`:

- **Header:** Subject, status badge, source, linked customer
- **Enquiry details:** Original message (read-only), internal notes section
- **Reply thread:** Reverse-chronological list of all replies (both outbound and future inbound). Each shows direction icon (sent/received), staff name or customer name, timestamp, body.
- **Compose form:** Subject (pre-filled from enquiry), body textarea, tone selector (if AI draft used), "Generate AI Draft" button, "Send" button
- **Linked quotes:** Card listing quotes linked to this enquiry, with "Create Quote" button
- **Activity log:** Timeline of status changes, replies sent, quotes created

### Route

```php
Route::get('enquiries/{enquiryId}', EnquiryShow::class)->name('enquiries.show');
```

### MCP Tools

**`GetEnquiryTool`** — read-only, single enquiry with:
- Customer, replies (chronological), quotes, staff assignment
- `outputSchema` includes thread state (has_unread_inbound, has_draft)

**`CreateEnquiryReplyTool`** — write, preview/confirmed:

| Param | Type | Description |
|-------|------|-------------|
| `enquiry_id` | int (required) | Enquiry to reply to |
| `subject` | string (nullable) | Subject line (defaults to enquiry subject with Re:) |
| `body` | string (required) | Reply body |
| `status` | string (default: `sent`) | `sent` or `draft` |
| `preview` | boolean (default true) | Preview flag |
| `confirmed` | boolean (default false) | Confirm flag |

**`ListEnquiryRepliesTool`** — read-only, returns paginated thread for a given enquiry.

---

## Phase 3 — Expanded Enquiry Functionality

### Migrations

**Add fields to `enquiries`:**
```php
Schema::table('enquiries', function (Blueprint $table) {
    $table->text('internal_notes')->nullable()->after('message');
    $table->string('email')->nullable()->after('customer_id');
    $table->string('phone')->nullable()->after('email');
});
```

**Create `enquiry_activity_logs`:**
```php
Schema::create('enquiry_activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('enquiry_id')->constrained()->onDelete('cascade');
    $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->string('action');          // status_changed, reply_sent, quote_created, assigned, note_added
    $table->text('description')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

### Model

**`App\Models\EnquiryActivityLog`:**
```php
class EnquiryActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['enquiry_id', 'staff_user_id', 'action', 'description', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function enquiry(): BelongsTo;
    public function staff(): BelongsTo;
}
```

### UI Changes

- **`EnquiryList`:** Add date range filter (`since`, `until`), staff assignment filter dropdown. Add inline "Assign to staff" dropdown per row.
- **`EnquiryForm`:** Add `internal_notes` textarea field.
- **`EnquiryShow`:** Activity log timeline display. Staff assignment dropdown in the header.
- **Unlinked enquiries:** Show email/phone fields on the form so staff can record contact info for walk-in/phone enquiries without creating a full customer record.

### Route Updates

Update the existing `edit` route to behave as a true edit (not the primary view). The show route becomes the canonical detail view.

---

## Phase 4 — AI Draft Assistant

### Settings

Add new setting to the existing `AiAssistantConfigSettings`:

```php
// app/Settings/AiAssistantConfigSettings.php — add:
public ?int $enquiry_draft_assistant_config_id = null;
```

Settings migration:
```php
$this->migrator->add('ai_assistant_config.enquiry_draft_assistant_config_id', null);
```

### Service: `EnquiryAiAssistantService`

Uses Prism with the configured `AiModelConfig` (falls back to env defaults if none configured).

```php
class EnquiryAiAssistantService
{
    public function generateDraft(Enquiry $enquiry, string $tone = 'professional'): AiDraftResult;

    public function buildPrompt(Enquiry $enquiry, string $tone): string;
}
```

**`AiDraftResult`** (value object or array shape):
```php
[
    'summary' => 'Customer needs a 200Ah lithium battery',
    'suggested_next_steps' => [
        'Provide a quote for a 200Ah Fogstar Drift battery',
        'Ask if they need installation or just supply',
    ],
    'draft_subject' => 'Re: Battery enquiry',
    'draft_body' => 'Hi [Name],\n\nThanks for your enquiry...',
    'confidence' => 'high|medium|low',
    'knowledge_gaps' => ['Customer did not specify budget'],
]
```

### Prompt: `resources/views/ai/prompts/enquiry-draft-assistant.blade.php`

```
You are a professional customer service assistant for Quantock Van Tech, a
campervan electrical installation business in West Somerset.

## Your Task
A staff member has asked you to draft a response to a customer enquiry.
Analyse the enquiry below and produce a structured response with:
1. A brief summary of what the customer needs
2. Suggested next steps (e.g. "Send a quote for...", "Ask about van model")
3. A professional draft reply subject line
4. A professional draft reply body
5. A confidence rating (high/medium/low)
6. Any knowledge gaps or missing information

## Rules
- NEVER send replies directly. You are generating a DRAFT for staff review.
- Use British English spelling and tone.
- Be polite, professional, and helpful.
- Do not invent prices or specific product details unless the enquiry mentions them.
- If unsure about something, flag it as a knowledge gap.
- The staff member will review, edit, and manually send your draft.

## Context
Enquiry from: {{ $enquiry->customer?->name ?? 'Unknown' }}
Subject: {{ $enquiry->subject ?? '(no subject)' }}
Message: {{ $enquiry->message }}

## Response Format
Return ONLY valid JSON in this exact structure — no markdown, no code fences:
{
    "summary": "...",
    "suggested_next_steps": ["...", "..."],
    "draft_subject": "...",
    "draft_body": "...",
    "confidence": "high|medium|low",
    "knowledge_gaps": ["..."]
}
```

### UI Integration

In `EnquiryShow`:
- "Generate AI Draft" button above the compose form
- Calls service via Livewire action → shows loading state
- On success: populates subject + body fields, shows "Suggested next steps" panel, shows confidence badge
- Staff edits the fields as needed, then clicks "Send"
- If confidence is low or knowledge gaps exist, show warning banner

### Safety Guardrails

1. **No auto-send** — AI service NEVER calls Postmark or sends email. It only returns structured text.
2. **Draft flagged** — `ai_draft_data` JSON column stores the full AI output alongside the reply for audit.
3. **Staff must click Send** — compose form requires explicit Send action, which calls `EnquiryReplyService::send()`.
4. **Confidence indicator** — UI shows high/medium/low badge. Low confidence triggers a review prompt.
5. **Knowledge gaps displayed** — shown as a list so staff know what to ask the customer before sending.

### MCP Tools

**`GenerateEnquiryDraftTool`** — idempotent, read-only (no DB writes unless explicitly saved):

| Param | Type | Description |
|-------|------|-------------|
| `enquiry_id` | int (required) | Enquiry to generate a draft for |
| `tone` | string (default: `professional`) | `professional` or `casual` |

Returns: AI draft data + human-readable summary message. Does NOT save anything to DB.

**`SaveEnquiryDraftTool`** — write, saves AI draft as a draft reply (status=draft):

| Param | Type | Description |
|-------|------|-------------|
| `enquiry_id` | int (required) | Enquiry to save draft for |
| `subject` | string (nullable) | Subject from AI or edited |
| `body` | string (required) | Body from AI or edited |
| `ai_draft_data` | json (nullable) | Original AI output for audit |

---

## Phase 5 — MCP Integration

### New Tools to Register in `QvtServer`

```php
// Enquiry expansion tools
GetEnquiryTool::class,
CreateEnquiryReplyTool::class,
ListEnquiryRepliesTool::class,
CreateQuoteFromEnquiryTool::class,
GenerateEnquiryDraftTool::class,
SaveEnquiryDraftTool::class,
```

### Update Chat Agent Prompt

Add to `resources/views/ai/prompts/chat-agent.blade.php`:
- Enquiry reply flow (draft → send)
- Quote-from-enquiry flow
- AI draft generation capability

### Route Registration

Add new show route to `routes/customers.php`:
```php
Route::get('enquiries/{enquiryId}', EnquiryShow::class)->name('enquiries.show');
```

---

## File Manifest

### New Files

```
database/migrations/2026_07_XX_XXXXXX_add_enquiry_id_to_quotes_table.php
database/migrations/2026_07_XX_XXXXXX_add_fields_to_enquiries_table.php
database/migrations/2026_07_XX_XXXXXX_create_enquiry_replies_table.php
database/migrations/2026_07_XX_XXXXXX_create_enquiry_activity_logs_table.php
database/settings/2026_07_XX_XXXXXX_add_enquiry_draft_setting.php

app/Models/EnquiryReply.php
app/Models/EnquiryActivityLog.php

app/Livewire/Enquiries/EnquiryShow.php
resources/views/livewire/enquiries/enquiry-show.blade.php

app/Services/EnquiryReplyService.php
app/Services/EnquiryAiAssistantService.php

resources/views/ai/prompts/enquiry-draft-assistant.blade.php

app/Mcp/Tools/GetEnquiryTool.php
app/Mcp/Tools/CreateEnquiryReplyTool.php
app/Mcp/Tools/ListEnquiryRepliesTool.php
app/Mcp/Tools/CreateQuoteFromEnquiryTool.php
app/Mcp/Tools/GenerateEnquiryDraftTool.php
app/Mcp/Tools/SaveEnquiryDraftTool.php
```

### Modified Files

```
app/Models/Enquiry.php                      — add quotes(), replies(), latestReply(), casts
app/Models/Quote.php                        — add enquiry() relationship, enquiry_id to fillable
app/Mcp/Servers/QvtServer.php               — register new tools
resources/views/ai/prompts/chat-agent.blade.php

app/Livewire/Enquiries/EnquiryList.php      — add date range filter, staff assignment
app/Livewire/Enquiries/EnquiryForm.php      — add internal_notes, email, phone fields
app/Livewire/Quotes/QuoteBuilder.php        — accept ?enquiryId= param

routes/customers.php                        — add enquiries.show route
```

---

## Testing Strategy

### Feature Tests (PHPUnit)

| Test File | Tests |
|-----------|-------|
| `EnquiryReplyTest.php` | Send reply preview, confirmed send creates reply + sends email, draft save, validation, idempotent |
| `EnquiryAiAssistantTest.php` | Draft generation returns correct schema, confidence levels, knowledge gaps, no DB write on generate |
| `EnquiryShowTest.php` | Livewire renders enquiry details, reply thread, linked quotes, AI draft button (if admin) |
| `QuoteFromEnquiryTest.php` | Quote created with correct `enquiry_id`, customer pre-filled, `enquiry_id` set on save |
| `EnquiryActivityLogTest.php` | Status changes, replies, and quote creations create activity log entries |
| `PermissionGatingTest.php` | Installer role cannot see/use new MCP tools, edit-only access to UI |

### Edge Cases

| Scenario | Handling |
|----------|----------|
| Enquiry has no linked customer | Reply requires `enquiry.email` to be set; form validates before send |
| Customer replies between staff composing | Thread still works — inbound reply appears chronologically |
| AI generates low-confidence draft | UI shows warning, staff can discard or edit |
| Quote created from enquiry, but enquiry already has quotes | Multiple quotes linked to one enquiry allowed (customer might get revisions) |
| Postmark send fails | Reply saved with `status: failed`, `error_message` set, staff can retry |
| Staff closes enquiry, then customer replies (future) | Inbound reply re-opens to `in_progress` status |
| `message_id` collision | UUID-based generation with enquiry+reply ID guarantees uniqueness |

---

## Future Considerations (Not in Scope)

- **Postmark inbound webhook route** — a single POST endpoint and lookup service to create inbound replies
- **Email forwarding** — an alias staff can forward customer emails to, which creates inbound replies
- **Conversation view** — richer UI with threaded display (nested replies, quote/forward indicators)
- **Auto-reply suggestions** — AI could suggest replies based on previous similar enquiries
- **Enquiry templates** — pre-written response templates for common scenarios
