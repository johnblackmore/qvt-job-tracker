# QVT Job Tracker — MCP Server Integration Plan

## Overview

This plan details how to add Model Context Protocol (MCP) server capabilities to the Quantock Van Tech Job Tracker using the official `laravel/mcp` package. The goal is to enable AI agents to interact with the application via natural language prompts for all staff admin operations.

**Examples of target interactions:**
- "Create a new customer record for John with email john@johnblackmore.com"
- "Create a solar panel installation quote for John"
- "Has John accepted his solar panel quote yet?"
- "Has John paid the deposit for his solar panel quote?"
- "Give me an update on all quote activity in the last week"

---

## Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Server Type** | Single unified web server + local server | Supports multiple AI clients (Claude Desktop via local, OpenCode/Cursor via web) |
| **Auth Method** | Laravel Sanctum API tokens | Simple, fits existing Breeze auth, token passed via `Authorization: Bearer` header |
| **Authorization** | `admin` role gate via `shouldRegister` + middleware | Only staff admin users see tools; zero customer access |
| **Transport** | Web: HTTP POST at `/mcp/qvt`; Local: `php artisan mcp:start qvt --user={id}` | MCP spec compliant; web for remote agents, local for desktop agents |
| **Confirmation** | Preview/confirmed pattern on all write tools | AI agent presents a preview and asks for approval before executing destructive changes |
| **Token Lifetime** | Long-lived, revocable | Staff generate tokens once and revoke from the admin profile when needed |

---

## Technology Stack

| Component | Technology |
|-----------|-----------|
| MCP Framework | `laravel/mcp` (^0.8) |
| Auth | `laravel/sanctum` (API tokens) |
| Roles | `spatie/laravel-permission` (existing) |
| Validation | Laravel Validation + JSON Schema |
| Responses | Structured JSON + human-readable text |

---

## Directory Structure

```
app/
  Mcp/
    Servers/
      QvtServer.php           # Unified MCP server definition
    Tools/
      Customers/
        CreateCustomerTool.php
        UpdateCustomerTool.php
        ListCustomersTool.php
        GetCustomerTool.php
        SearchCustomersTool.php
      Quotes/
        CreateQuoteTool.php
        CreateQuoteFromTemplateTool.php
        UpdateQuoteStatusTool.php
        ListQuotesTool.php
        GetQuoteTool.php
        SearchQuotesTool.php
        SendQuoteEmailTool.php
        DownloadQuotePdfTool.php
      Orders/
        CreateOrderTool.php
        UpdateOrderStatusTool.php
        ListOrdersTool.php
        GetOrderTool.php
        UpdateDepositTool.php
      Products/
        ListProductsTool.php
        GetProductTool.php
        SearchProductsTool.php
      Enquiries/
        ListEnquiriesTool.php
        CreateEnquiryTool.php
        LinkEnquiryToCustomerTool.php
      Dashboard/
        GetDashboardStatsTool.php
        GetQuoteActivityTool.php
    Resources/
      CustomerResource.php
      QuoteResource.php
      OrderResource.php
      ProductCatalogResource.php
      QuotePdfResource.php          # Template resource for PDF access
    Prompts/
      QuoteAssistantPrompt.php
      CustomerServicePrompt.php
routes/
  ai.php                          # MCP route registrations (published from package)
```

---

## Authentication & Authorization Strategy

### 1. Install & Configure Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

- Add `HasApiTokens` to the `User` model.
- Staff users generate personal access tokens via a new "API Tokens" section in their profile page.
- Tokens should be named (e.g., "OpenCode Agent", "Cursor Agent") so users can revoke them individually.
- Tokens are **long-lived** (no expiry by default) and revocable from the staff profile.

### 2. MCP Route Registration (`routes/ai.php`)

```php
use App\Mcp\Servers\QvtServer;
use Laravel\Mcp\Facades\Mcp;

// Local server for Claude Desktop / stdio-based clients
// Requires --user flag to identify the staff user
Mcp::local('qvt', QvtServer::class);

// Web server for remote / HTTP-based clients (OpenCode, Cursor, custom agents)
Mcp::web('/mcp/qvt', QvtServer::class)
    ->middleware([
        'auth:sanctum',
        'role:admin',          // Spatie role middleware
        'throttle:mcp',        // Custom rate limiter
    ]);
```

### 3. Local Server User Flag

When running the local MCP server, the staff user ID must be passed so all write actions are attributed to the correct user:

```bash
php artisan mcp:start qvt --user=1
```

The local server bootstraps the application as that user before exposing tools. The `shouldRegister()` checks and audit logging then use this authenticated user. If `--user` is omitted, the server starts but no tools are registered (safe default).

### 4. Role-Based Tool Gating

Every tool implements `shouldRegister` to verify the authenticated user has the `admin` role:

```php
public function shouldRegister(Request $request): bool
{
    return $request->user()?->hasRole('admin') ?? false;
}
```

This ensures that even if a non-admin token is presented, no tools are exposed.

### 5. Additional Security Measures

- All write tools annotated with `#[IsIdempotent]` where applicable (e.g., update status).
- Read tools annotated with `#[IsReadOnly(true)]`.
- No trade prices exposed in any tool response unless explicitly marked internal.
- All tools validate input via `$request->validate()` with clear, actionable error messages.

---

## Confirmation / Approval Flow for Write Actions

All tools that create, update, delete, or change status must support a **preview** mode. The AI agent must show the user what will happen and ask for confirmation before executing.

### Preview Pattern

Every write tool accepts two boolean parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `preview` | boolean | `true` | When `true`, validates input and returns what *would* happen without touching the database. |
| `confirmed` | boolean | `false` | When `true`, the user has approved the preview and the action should execute. |

**Behavior:**

- **`preview: true` (default)** — Validates all input, queries related data, and returns a structured **preview response**. No database writes occur.
- **`confirmed: true`** — Executes the actual database change after validation passes. If `preview: true` is also passed, `confirmed` takes precedence.

**Error if user skips preview:**
If a tool receives `preview: false` and `confirmed: false`, it returns an error: *"This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed."*

### Example Conversation

> **User:** Create a customer record for John with email john@johnblackmore.com
>
> **AI -> calls `create-customer`** (`preview: true`, `confirmed: false`)
> ```json
> {
>   "status": "preview",
>   "action": "create_customer",
>   "message": "I will create a new customer record.\n\nName: John\nEmail: john@johnblackmore.com\n\nIs that correct?",
>   "data": { "name": "John", "email": "john@johnblackmore.com" }
> }
> ```
>
> **AI -> asks user:** "I will create a new customer record. Name: John. Email: john@johnblackmore.com. Is that correct?"
>
> **User:** Yes
>
> **AI -> calls `create-customer`** (`preview: false`, `confirmed: true`)
> ```json
> {
>   "status": "completed",
>   "message": "I have created a new customer record for John.",
>   "customer": { "id": 42, "name": "John", "email": "john@johnblackmore.com" }
> }
> ```

### Edge Cases

| Scenario | Handling |
|----------|----------|
| **Ambiguous customer name** | Preview tool finds multiple matches and returns: "I found 3 customers named John. Did you mean: (1) John Smith, (2) John Blackmore, (3) John Doe?" AI asks user to clarify. |
| **Missing required fields** | Validation fails in preview mode, returning error before any confirmation is asked. |
| **Duplicate email** | Preview catches the unique constraint violation and suggests: "A customer with this email already exists. Did you mean to update John Blackmore instead?" |
| **Complex quotes** | `create-quote-from-template` preview lists all line items that will be cloned with current retail prices. |
| **Destructive actions** | `delete-customer` preview warns: "This will permanently delete John Blackmore and all linked quotes/orders. This cannot be undone." |

---

## Staff Configuration Guide

A new "AI Agent Access" page is added to the staff profile area.

### Step-by-Step for Staff Users

1. **Navigate to Profile -> API Tokens**
   - Page shows existing tokens with name, created date, last used, and revoke button.
   - Button: "Generate New Token"

2. **Generate Token**
   - Enter a descriptive name: e.g., "OpenCode Desktop", "Cursor Editor"
   - Token is displayed **once** — copy it immediately
   - Token is long-lived and revocable from this page

3. **Configure Your AI Client**

   **OpenCode** (Remote / Web Server):
   Add to `.opencode/opencode.json`:
   ```json
   {
     "mcp": {
       "qvt-job-tracker": {
         "type": "remote",
         "url": "https://quantockvantech.com/mcp/qvt",
         "headers": {
           "Authorization": "Bearer ${env:QVT_API_TOKEN}"
         }
       }
     }
   }
   ```
   Staff user copies their Sanctum token to the `QVT_API_TOKEN` environment variable.

   **Claude Desktop** (Local / stdio Server):
   Add to `claude_desktop_config.json`:
   ```json
   {
     "mcpServers": {
       "qvt-job-tracker": {
         "command": "php",
         "args": [
           "artisan",
           "mcp:start",
           "qvt",
           "--user=1"
         ],
         "cwd": "/Users/johnblackmore/Sites/qvt-job-tracker"
       }
     }
   }
   ```
   Replace `--user=1` with the actual staff user ID. The local server uses this user for all tool `shouldRegister` checks and audit logging.

   **Cursor** (Remote / Web Server):
   In Cursor MCP settings, add an HTTP server:
   - **URL**: `https://quantockvantech.com/mcp/qvt`
   - **Headers**: `Authorization: Bearer <sanctum-token>`
   - **Name**: `QVT Job Tracker`

4. **Test the Connection**
   - Ask the agent: "Show me today's dashboard stats"
   - If unauthorized, check the token is copied correctly and the user has the `admin` role

5. **Revoke Access**
   - Delete the token from the profile page — instant revocation
   - For local servers, change the `--user` flag or stop the server

---

## Tool Inventory

### Customer Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `create-customer` | Create a new customer record | Write | name, email, phone, address, notes, preview, confirmed | Customer JSON / Preview JSON |
| `update-customer` | Update customer details | Write | id, name, email, phone, address, notes, preview, confirmed | Updated customer JSON / Preview JSON |
| `list-customers` | List customers with pagination | Read | per_page, page, sort | Paginated customers |
| `get-customer` | Get single customer by ID | Read | id | Customer with vehicles, quotes, orders |
| `search-customers` | Fuzzy search by name/email | Read | query | Matching customers |
| `delete-customer` | Soft delete a customer | Write (destructive) | id, preview, confirmed | Confirmation / Preview JSON |

### Quote Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `create-quote` | Create a blank quote for a customer | Write | customer_id, notes, valid_until, preview, confirmed | Quote JSON / Preview JSON |
| `create-quote-from-template` | Clone a sample quote to a real quote | Write | sample_quote_id, customer_id, preview, confirmed | Quote with line items / Preview JSON |
| `add-quote-line-item` | Add a product/labour/ad-hoc line | Write | quote_id, line_type, product_id, quantity, description, preview, confirmed | Updated quote / Preview JSON |
| `update-quote-status` | Change quote status (draft->sent->accepted...) | Write | id, status, preview, confirmed | Updated quote / Preview JSON |
| `list-quotes` | List quotes with filters | Read | status, customer_id, since, per_page | Paginated quotes |
| `get-quote` | Get quote with full line items | Read | id | Quote with line items, totals |
| `search-quotes` | Search by reference or customer | Read | query | Matching quotes |
| `send-quote-email` | Send quote PDF via email template | Write | quote_id, template_id, custom_message, preview, confirmed | Email sent confirmation / Preview JSON |
| `download-quote-pdf` | Generate and return PDF content | Read | quote_id | PDF binary or URL |

### Order Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `create-order` | Create an order (optionally from a quote) | Write | customer_id, quote_id, total_amount, deposit_required, preview, confirmed | Order JSON / Preview JSON |
| `update-order-status` | Change order status | Write | id, status, preview, confirmed | Updated order / Preview JSON |
| `update-deposit` | Record a deposit payment | Write | id, deposit_paid, preview, confirmed | Updated order with balance / Preview JSON |
| `list-orders` | List orders with filters | Read | status, customer_id, since, per_page | Paginated orders |
| `get-order` | Get order details | Read | id | Order with customer, quote, deposit % |
| `schedule-installation` | Set/change scheduled date | Write | id, scheduled_date, preview, confirmed | Updated order / Preview JSON |

### Product Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `list-products` | List products with filtering | Read | category_id, search, is_active, per_page | Paginated products |
| `get-product` | Get product with suppliers | Read | id | Product with supplier pivots (trade prices only if admin) |
| `search-products` | Search product catalogue | Read | query | Matching products |

### Enquiry Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `list-enquiries` | List enquiries with filters | Read | status, source, since, per_page | Paginated enquiries |
| `create-enquiry` | Log a new enquiry | Write | source, subject, message, customer_id, preview, confirmed | Enquiry JSON / Preview JSON |
| `link-enquiry-to-customer` | Link an enquiry to a customer | Write | enquiry_id, customer_id, preview, confirmed | Updated enquiry / Preview JSON |
| `respond-to-enquiry` | Mark enquiry as responded | Write | id, staff_user_id, preview, confirmed | Updated enquiry / Preview JSON |

### Dashboard / Reporting Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `get-dashboard-stats` | High-level business stats | Read | - | Stats JSON |
| `get-quote-activity` | Quote changes in date range | Read | since, until | Activity summary |
| `get-weekly-summary` | Full weekly business update | Read | - | Narrative + structured data |

---

## Resource Inventory

Resources provide contextual data that AI clients can read as background context.

| Resource | URI Pattern | Description |
|----------|-------------|-------------|
| `customer-profile` | `qvt://customers/{id}` | Full customer profile |
| `quote-details` | `qvt://quotes/{id}` | Quote with line items (retail prices only) |
| `order-details` | `qvt://orders/{id}` | Order with deposit status |
| `product-catalogue` | `qvt://products/catalogue` | Full active product list |
| `business-guidelines` | `qvt://guidelines/business-rules` | Static resource with trade-price confidentiality rules and business logic |

---

## Prompt Inventory

Prompts provide reusable templates that help the AI client structure interactions.

| Prompt | Description | Arguments |
|--------|-------------|-----------|
| `quote-assistant` | System prompt for quote-related tasks | tone (professional/casual) |
| `customer-service-assistant` | System prompt for customer interactions | tone |
| `weekly-report-generator` | Template for generating weekly summaries | week_starting |

---

## Implementation Phases

### Phase 1: Foundation & Auth
1. Install `laravel/mcp` and `laravel/sanctum`
2. Publish `routes/ai.php`
3. Create `QvtServer` class
4. Configure Sanctum on `User` model
5. Add API token generation UI to staff profile page
6. Register both local and web servers in `routes/ai.php`
7. Create `AdminRoleMiddleware` or use Spatie's existing role middleware
8. Add `--user` flag handling for local MCP server
9. Write feature test: MCP server is unreachable without valid token

### Phase 2: Customer & Product Read Tools
1. `list-customers`, `get-customer`, `search-customers`
2. `list-products`, `get-product`, `search-products`
3. Add `[IsReadOnly(true)]` annotations
4. Write tests: tools return correct data, respect pagination

### Phase 3: Customer & Quote Write Tools
1. `create-customer`, `update-customer`
2. `create-quote`, `create-quote-from-template`
3. `add-quote-line-item`
4. `update-quote-status`
5. Implement preview/confirmed pattern on all write tools
6. Write tests: creation flows, status transitions, template cloning

### Phase 4: Order & Enquiry Tools
1. `create-order`, `update-order-status`, `update-deposit`
2. `schedule-installation`
3. `list-enquiries`, `create-enquiry`, `link-enquiry-to-customer`
4. Write tests: order creation from quote, deposit math, status transitions

### Phase 5: Communication & PDF Tools
1. `send-quote-email`
2. `download-quote-pdf`
3. `respond-to-enquiry`
4. Write tests: email tracking, PDF generation via MCP

### Phase 6: Dashboard & Reporting Tools
1. `get-dashboard-stats`
2. `get-quote-activity`
3. `get-weekly-summary`
4. Add resources (`customer-profile`, `quote-details`, `order-details`)
5. Add prompts (`quote-assistant`, `weekly-report-generator`)
6. Write tests: activity aggregation, narrative generation

### Phase 7: Polish & Security Hardening
1. Add rate limiting (`throttle:mcp`)
2. Audit all tools for trade-price leakage
3. Ensure all write tools have clear confirmation/error messages
4. Add `IsDestructive` annotation to delete operations
5. Full test suite run
6. Document MCP client configuration for Claude, Cursor, OpenCode

---

## Security Checklist

| Requirement | Implementation |
|-------------|------------------|
| **Staff only** | `auth:sanctum` + `shouldRegister` checks `hasRole('admin')` |
| **No customer access** | No customer-facing endpoints; MCP routes are not published to customers |
| **Token management** | Tokens generated in staff profile; revocable; long-lived by design |
| **Trade price confidentiality** | Product/quote tools only expose retail prices in tool responses; trade prices never leave internal tools |
| **Rate limiting** | `throttle:mcp` middleware with configurable limits per minute |
| **Audit trail** | Critical write tools log actions to `emails_sent` or application log with staff_user_id |
| **Input validation** | All tools use `$request->validate()` with strict rules |
| **Idempotency** | Status update tools are idempotent; create tools return existing records on duplicate unique keys |

---

## Trade Price Confidentiality Enforcement

Because the MCP server acts as a staff interface, it **can** expose trade prices to authenticated admin users. However, any tool that could be used for customer-facing output must only use retail prices:

- `get-quote` — includes both retail and trade totals in structured response; clearly labeled as `internal_trade_total`
- `download-quote-pdf` — calls existing `QuotePdfController` which already strips trade prices
- `send-quote-email` — uses existing `QuoteEmailService` which already strips trade prices
- `get-product` — includes supplier trade prices in `suppliers` array (staff-only context)

If a future customer portal MCP server is ever created, it must use a **separate server** with a **separate auth gate** and **zero trade price exposure**.

---

## Testing Strategy

1. **Feature tests for every tool:**
   - Happy path returns expected structured data
   - Validation errors return clear messages
   - Unauthorized access returns no tools / 401
   - Non-admin user sees no tools
2. **Preview mode tests:**
   - Preview returns correct data with no DB changes
   - Preview includes human-readable confirmation message
   - Confirmed mode performs the action correctly
   - Calling without preview or confirmed returns instructional error
3. **Integration tests for cross-model workflows:**
   - Create customer -> create quote from template -> send email -> check activity
   - Create quote -> convert to order -> pay deposit -> check status
4. **Security tests:**
   - Trade prices do not appear in quote PDF or email tools
   - Rate limiting triggers after threshold
   - Local server with invalid --user flag exposes no tools

---

## Future Extensions (Post-Phase 1)

- **Customer Portal MCP Server** (separate auth, no trade prices)
- **Calendar/Scheduling tools** (Google Calendar / Outlook integration)
- **Inventory alerts** (low-stock product notifications via MCP)
- **Batch operations** (bulk quote creation, bulk email sending)

---

## Appendix: Sample Tool Implementation Sketch

### `CreateCustomerTool` (with Preview/Confirmed Pattern)

```php
namespace App\Mcp\Tools\Customers;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new customer record in the QVT Job Tracker. Requires confirmation.')]
class CreateCustomerTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Full customer name')->required(),
            'email' => $schema->string()->description('Customer email address')->required(),
            'phone' => $schema->string()->description('Phone number')->nullable(),
            'address' => $schema->string()->description('Physical address')->nullable(),
            'notes' => $schema->string()->description('Internal notes')->nullable(),
            'preview' => $schema->boolean()->description('Set true to preview what will happen without saving.')->default(true),
            'confirmed' => $schema->boolean()->description('Set true to confirm and execute the action after preview.')->default(false),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:5000',
            'preview' => 'boolean',
            'confirmed' => 'boolean',
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error(
                'This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed.'
            );
        }

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'action' => 'create_customer',
                'message' => "I will create a new customer record.\n\nName: {$validated['name']}\nEmail: {$validated['email']}\n\nIs that correct?",
                'data' => [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'notes' => $validated['notes'],
                ],
            ]);
        }

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new customer record for {$customer->name}.",
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'created_at' => $customer->created_at->toIso8601String(),
                'url' => route('customers.show', $customer),
            ],
        ]);
    }
}
```

---

## AGENTS.md Update: MCP Server Maintenance Rules

When building or modifying staff admin functionality, agents must maintain parity between the web UI and the MCP server interface.

### Rules

1. **New Feature = New Tool**: Any new CRUD operation, status transition, or business action added to a Livewire component must have a corresponding MCP tool registered in `QvtServer`.
2. **Schema Parity**: When modifying validation rules, fillable fields, or business logic in a controller/Livewire form, update the matching MCP tool's `schema()` and `handle()` methods.
3. **Preview Pattern**: All new write tools must implement the `preview` / `confirmed` boolean parameter pattern (default `preview=true`, `confirmed=false`) to enable confirmation flows.
4. **Trade Price Rule**: Never expose `trade_price` or `total_trade` in any MCP tool response that could be used for customer-facing output. Internal-only read tools may expose trade data if clearly labeled.
5. **Route Registration**: New tool categories should be grouped in the `QvtServer::$tools` array with clear namespacing.
6. **Testing**: Every new tool must have a PHPUnit feature test covering:
   - Preview mode returns correct preview data with no DB changes
   - Execute mode (`confirmed: true`) performs the action correctly
   - Validation errors return clear, actionable messages
   - Unauthenticated / non-admin requests return 401 / empty tool list

### MCP Directory Conventions

- `app/Mcp/Servers/` — Server definitions
- `app/Mcp/Tools/{Domain}/` — Grouped by business domain (Customers, Quotes, Orders, etc.)
- `app/Mcp/Resources/` — Read-only contextual data
- `app/Mcp/Prompts/` — Reusable AI prompt templates
- `routes/ai.php` — MCP route registration

### Updating AGENTS.md

If you add new business domains (e.g., Invoicing, Calendar), add the new tool category to the plan in `.opencode/plans/qvt-mcp-server-plan.md` and update this section.

---

*End of plan.*
