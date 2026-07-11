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
| **Response Links** | Named route URLs in all record responses | Every tool returning a record includes a `url` field so agents can present clickable links back to staff |
| **Chat-Forward Design** | Programmatic tool calls + natural-language messages | Tools return structured data + human-readable `message` + `url` so a future chat UI can consume them directly |

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

## Response Link Standard

Every tool that returns a single record (customer, quote, order, enquiry, vehicle, product) or operates on a single record (create, update, delete, status change) must include a `url` field pointing to the staff admin view page.

### Rule

| Tool Type | Required Response Fields |
|-----------|--------------------------|
| **Create/Update/Delete** | `status`, `message`, `url` + record data |
| **Get by ID** | Record data + `url` |
| **List/Search** | Each item in the array must include `url` |
| **Preview** | `status: "preview"`, `message`, `data` (no `url` until confirmed) |

### Named Routes

Use existing named routes from the web UI:

```php
// Customer
'url' => route('customers.show', $customer)

// Quote
'url' => route('quotes.show', $quote)

// Order
'url' => route('orders.show', $order)

// Enquiry
'url' => route('enquiries.show', $enquiry)
```

### Example: Create Customer Response (Confirmed)

```json
{
  "status": "completed",
  "message": "I have created a new customer record for John.",
  "url": "http://localhost/customers/42",
  "customer": {
    "id": 42,
    "name": "John",
    "email": "john@johnblackmore.com"
  }
}
```

The AI agent can present this as a clickable card or button in the chat interface: **"View John in Customer List"**.

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
| `create-customer` | Create a new customer record | Write | name, email, phone, address, notes, preview, confirmed | Customer JSON + `message` + `url` / Preview JSON |
| `update-customer` | Update customer details | Write | id, name, email, phone, address, notes, preview, confirmed | Updated customer JSON + `message` + `url` / Preview JSON |
| `list-customers` | List customers with pagination | Read | per_page, page, sort | Paginated customers (each item has `url`) |
| `get-customer` | Get single customer by ID | Read | id | Customer with `url`, vehicles, quotes, orders |
| `search-customers` | Fuzzy search by name/email | Read | query | Matching customers (each item has `url`) |
| `delete-customer` | Soft delete a customer | Write (destructive) | id, preview, confirmed | Confirmation + `message` + `url` / Preview JSON |

### Quote Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `create-quote` | Create a blank quote for a customer | Write | customer_id, notes, valid_until, preview, confirmed | Quote JSON + `message` + `url` / Preview JSON |
| `create-quote-from-template` | Clone a sample quote to a real quote | Write | sample_quote_id, customer_id, preview, confirmed | Quote with line items + `message` + `url` / Preview JSON |
| `add-quote-line-item` | Add a product/labour/ad-hoc line | Write | quote_id, line_type, product_id, quantity, description, preview, confirmed | Updated quote + `message` + `url` / Preview JSON |
| `update-quote-status` | Change quote status (draft->sent->accepted...) | Write | id, status, preview, confirmed | Updated quote + `message` + `url` / Preview JSON |
| `list-quotes` | List quotes with filters | Read | status, customer_id, since, per_page | Paginated quotes (each item has `url`) |
| `get-quote` | Get quote with full line items | Read | id | Quote with `url`, line items, totals |
| `search-quotes` | Search by reference or customer | Read | query | Matching quotes (each item has `url`) |
| `send-quote-email` | Send quote PDF via email template | Write | quote_id, template_id, custom_message, preview, confirmed | Email sent confirmation + `message` + `url` / Preview JSON |
| `download-quote-pdf` | Generate and return PDF content | Read | quote_id | PDF binary or URL |

### Order Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `create-order` | Create an order (optionally from a quote) | Write | customer_id, quote_id, total_amount, deposit_required, preview, confirmed | Order JSON + `message` + `url` / Preview JSON |
| `update-order-status` | Change order status | Write | id, status, preview, confirmed | Updated order + `message` + `url` / Preview JSON |
| `update-deposit` | Record a deposit payment | Write | id, deposit_paid, preview, confirmed | Updated order with balance + `message` + `url` / Preview JSON |
| `list-orders` | List orders with filters | Read | status, customer_id, since, per_page | Paginated orders (each item has `url`) |
| `get-order` | Get order details | Read | id | Order with `url`, customer, quote, deposit % |
| `schedule-installation` | Set/change scheduled date | Write | id, scheduled_date, preview, confirmed | Updated order + `message` + `url` / Preview JSON |

### Product Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `list-products` | List products with filtering | Read | category_id, search, is_active, per_page | Paginated products (each item has `url`) |
| `get-product` | Get product with suppliers | Read | id | Product with `url`, supplier pivots (trade prices staff-only) |
| `search-products` | Search product catalogue | Read | query | Matching products (each item has `url`) |

### Enquiry Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `list-enquiries` | List enquiries with filters | Read | status, source, since, per_page | Paginated enquiries (each item has `url`) |
| `create-enquiry` | Log a new enquiry | Write | source, subject, message, customer_id, preview, confirmed | Enquiry JSON + `message` + `url` / Preview JSON |
| `link-enquiry-to-customer` | Link an enquiry to a customer | Write | enquiry_id, customer_id, preview, confirmed | Updated enquiry + `message` + `url` / Preview JSON |
| `respond-to-enquiry` | Mark enquiry as responded | Write | id, staff_user_id, preview, confirmed | Updated enquiry + `message` + `url` / Preview JSON |

### Dashboard / Reporting Tools

| Tool | Description | Write/Read | Key Params | Returns |
|------|-------------|------------|------------|---------|
| `get-dashboard-stats` | High-level business stats | Read | - | Stats JSON + `message` |
| `get-quote-activity` | Quote changes in date range | Read | since, until | Activity summary + `message` |
| `get-weekly-summary` | Full weekly business update | Read | - | Narrative + structured data + `message` |

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

## Phase 2: Chat Interface (Future — Factored into Phase 1)

A staff chat interface will be added inside the admin area so users can type natural language (e.g., "Create a quote for John") and have the system execute actions via the MCP tools. To make Phase 2 straightforward, Phase 1 must build the tools with the following constraints.

### How the Chat Interface Will Work

1. Staff user types a message in the chat UI
2. A backend controller receives the message and sends it to an external LLM API (e.g., OpenAI, Anthropic) with the full list of available MCP tools and their descriptions
3. The LLM classifies intent, selects the appropriate tool(s), and extracts parameters
4. The backend calls the tool programmatically via the Laravel MCP Client:
   ```php
   $client = Mcp::client('qvt'); // Connects to the local QvtServer
   $result = $client->callTool('create-customer', ['name' => 'John', ...]);
   ```
5. The backend receives the tool result (structured JSON + `message` + `url`) and renders it in the chat as a message bubble + action button
6. If the tool returns `status: "preview"`, the chat UI asks the user to confirm before re-calling with `confirmed: true`

### Phase 1 Design Requirements for Chat Compatibility

| Requirement | Phase 1 Implementation |
|-------------|------------------------|
| **Programmatic tool calls** | Tools must be callable via `Mcp::client('qvt')->callTool()` without relying on HTTP request context for core logic. Use `Request` only for input extraction, not for auth or state. |
| **Natural-language descriptions** | Every tool `#[Description]` must be clear enough for an LLM to select it from a list. Example: *"Create a new customer record for the QVT Job Tracker. Requires confirmation."* |
| **Structured + message + url** | Every tool response must include: `message` (human-readable), `url` (link to view), and `status` (preview/completed/error). The chat UI will display `message` and render `url` as a button. |
| **Output schemas** | Every tool should define `outputSchema()` so the LLM knows exactly what to expect and the chat UI can parse reliably. |
| **No HTTP-only dependencies** | Tools must not rely on `session()`, `cookie()`, or `request()->ip()` for business logic. Chat calls bypass HTTP entirely. |
| **Consistent error format** | Validation errors and business errors must return `Response::error('clear natural language message')` so the chat UI can display them directly to the user. |
| **Audit logging** | All write tools must log `staff_user_id` explicitly (passed as param or resolved from auth). The chat controller will set the acting user before calling tools. |

### Chat UI Mock (Future)

```
┌─────────────────────────────────────────┐
│  QVT Assistant                          │
├─────────────────────────────────────────┤
│                                         │
│  User: Create a customer for John         │
│                                         │
│  Bot: I will create a new customer       │
│       record.                            │
│       Name: John                         │
│       Email: (not provided)              │
│                                         │
│       [Confirm]  [Edit]  [Cancel]        │
│                                         │
│  User: Confirm                           │
│                                         │
│  Bot: I have created a new customer      │
│       record for John.                   │
│                                         │
│       [View John in Customer List]       │
│                                         │
└─────────────────────────────────────────┘
```

The `[View John in Customer List]` button uses the `url` from the tool response.

---

## Implementation Progress

| Phase | Status | Tools / Deliverables |
|-------|--------|---------------------|
| **Phase 1: Foundation & Auth** | ✅ Complete | `laravel/mcp` + `laravel/sanctum` installed, `QvtServer` created, `routes/ai.php` registered (web + local), `McpSanctumAuth` middleware, `mcp.sanctum` guard, `throttle:mcp` rate limiter, `--user=email` flag for local server, `ApiTokenManager` Livewire component with sidebar nav, `QvtServerAuthTest` (3 tests) |
| **Phase 2: Customer & Product Read Tools** | ✅ Complete | `list-customers`, `get-customer`, `search-customers`, `list-products`, `get-product`, `search-products`. All `#[IsReadOnly(true)]`, all return `message` + `url` (or `data` + `pagination`). `get-product` includes `internal_trade_price`. `CustomerToolTest` (9 tests), `ProductToolTest` (7 tests) |
| **Phase 3: Customer & Quote Write Tools** | ⏳ Pending | `create-customer`, `update-customer`, `create-quote`, `create-quote-from-template`, `add-quote-line-item`, `update-quote-status` |
| **Phase 4: Order & Enquiry Tools** | ⏳ Pending | `create-order`, `update-order-status`, `update-deposit`, `schedule-installation`, `list-enquiries`, `create-enquiry`, `link-enquiry-to-customer` |
| **Phase 5: Communication & PDF Tools** | ⏳ Pending | `send-quote-email`, `download-quote-pdf`, `respond-to-enquiry` |
| **Phase 6: Dashboard & Reporting Tools** | ⏳ Pending | `get-dashboard-stats`, `get-quote-activity`, `get-weekly-summary`, resources, prompts |
| **Phase 7: Polish & Security Hardening** | ⏳ Pending | Rate limit audit, trade-price audit, confirmation messages, `IsDestructive` on deletes, full test suite, client config docs |

---

## Completed Phase Details

### Phase 1: Foundation & Auth — ✅ COMPLETE
- [x] Install `laravel/mcp` (^0.8.2) and `laravel/sanctum` (^4.3)
- [x] Publish `routes/ai.php`
- [x] Publish Sanctum migration (`personal_access_tokens` table)
- [x] Create `QvtServer` class with chat-forward design
- [x] Add `HasApiTokens` to `User` model
- [x] Register web server at `/mcp/qvt` with `mcp.sanctum` + `role:admin` + `throttle:mcp` middleware
- [x] Register local server `qvt` with `--user=email` flag support
- [x] Create `McpSanctumAuth` middleware (returns JSON-RPC 401, avoids redirect issues)
- [x] Create `McpServiceProvider` with `UserResolver` for local server auth
- [x] Add `mcp` rate limiter in `AppServiceProvider` (60/min)
- [x] Create `ApiTokenManager` Livewire component + view (create, revoke, copy tokens)
- [x] Add `/admin/api-tokens` route
- [x] Add "AI Agent Access" sidebar nav link with `key` icon
- [x] Write `QvtServerAuthTest`: 401 unauth, 403 non-admin, 200 admin
- [x] Run `pint` — clean

### Phase 2: Customer & Product Read Tools — ✅ COMPLETE
- [x] `ListCustomersTool` — paginated, search by name/email/phone, `per_page`/`page`, returns `data[]` + `pagination`
- [x] `GetCustomerTool` — `id` param, returns full customer with vehicles, enquiries, quotes, orders + `url`
- [x] `SearchCustomersTool` — `query` param, paginated, returns matching customers + `pagination`
- [x] `ListProductsTool` — filter by `category_id`/`is_active`, paginated, returns `data[]` + `pagination`
- [x] `GetProductTool` — `id` param, returns product with category + suppliers (including `internal_trade_price`) + `url`
- [x] `SearchProductsTool` — `query` param, filter by `category_id`, paginated, returns matching products + `pagination`
- [x] All 6 tools registered in `QvtServer::$tools`
- [x] All 6 tools implement `shouldRegister()` with `hasRole('admin')`
- [x] All 6 tools annotated with `#[IsReadOnly(true)]`
- [x] All 6 tools define `outputSchema()` for chat-forward compatibility
- [x] `handle()` return type widened to `Response|ResponseFactory` to support `Response::structured()`
- [x] Created `SupplierFactory` and `VehicleFactory` for testing
- [x] `CustomerToolTest`: 9 tests (pagination, URL inclusion, search, validation, full record, missing ID error, search match, read-only, role gating)
- [x] `ProductToolTest`: 7 tests (pagination, category filter, active filter, product with suppliers, trade price inclusion, search, read-only)
- [x] Updated `QvtServerAuthTest` to assert 6 tools are registered for admin users
- [x] Run `pint` — clean
- [x] All MCP tests passing: **19 tests, 96 assertions**

---

## Remaining Implementation Phases

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
3. **Response link tests:**
   - Every create/update/get tool response includes a `url` field with valid named route
   - List/search responses include `url` on every item
   - URL resolves to the correct staff admin show page
4. **Chat-forward tests:**
   - Every tool response includes a `message` field
   - Error responses are human-readable and suitable for chat UI display
   - Tool descriptions are clear enough for LLM intent classification
5. **Integration tests for cross-model workflows:**
   - Create customer -> create quote from template -> send email -> check activity
   - Create quote -> convert to order -> pay deposit -> check status
6. **Security tests:**
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

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
            'customer' => $schema->object([
                'id' => $schema->integer(),
                'name' => $schema->string(),
                'email' => $schema->string(),
                'phone' => $schema->string()->nullable(),
                'created_at' => $schema->string(),
            ])->nullable(),
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
            'url' => route('customers.show', $customer),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'created_at' => $customer->created_at->toIso8601String(),
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
6. **Response Links**: Every tool returning a single record must include a `url` field using `route('model.show', $record)`. List responses must include `url` on each item.
7. **Chat-Forward Design**: Every tool must return a `message` field with natural language suitable for a chat UI. Error messages must be human-readable. Tool `#[Description]` attributes must be clear enough for an LLM to select the tool from a list.
8. **Testing**: Every new tool must have a PHPUnit feature test covering:
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
