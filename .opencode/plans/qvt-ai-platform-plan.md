# QVT Job Tracker — AI Platform Plan

## Overview

Build an extensible AI platform within the QVT Job Tracker admin area, starting with a "Create from URL" assistant for the product creation flow, and designed from day one to support future assistants (including a full chat widget).

**Guiding principles:**
- **Provider-agnostic via Prism**: Use `prism-php/prism` for unified LLM access — covers OpenRouter, OpenAI, Anthropic, Ollama (for OpenCode local models), and more.
- **Assistant pattern**: Each AI capability is a dedicated "assistant" class, easily registered and configured.
- **Audit trail**: All AI operations logged in a database table for debugging, cost tracking, and reproducibility.
- **In-product UX**: Assistants embedded directly in existing Livewire forms (modals, drawers) — not separate pages.
- **Trade-safe**: Extracted retail data only; no trade prices in AI responses.

---

## Phase 1: Foundation — Prism + Config

### 1.1 Install Prism

```bash
composer require prism-php/prism
php artisan vendor:publish --provider="Prism\Prism\PrismServiceProvider"
```

This publishes `config/prism.php` which already supports **OpenRouter**, **OpenAI**, **Anthropic**, **Ollama** (for OpenCode local models), **Mistral**, **Groq**, **xAI**, **Gemini**, **DeepSeek**, **Perplexity**, and more.

### 1.2 Environment Configuration

```env
# OpenRouter (primary provider for cloud models)
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_URL=https://openrouter.ai/api/v1

# OpenCode / local models (via Ollama-compatible API)
OLLAMA_URL=http://localhost:11434

# Assistant-specific overrides
AI_URL_EXTRACTOR_PROVIDER=openrouter
AI_URL_EXTRACTOR_MODEL=openrouter/free
```

No need to add providers to `services.php` — Prism's config already handles them.

### 1.3 Application Config (`config/ai.php`)

```php
<?php
return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openrouter'),
    'default_model' => env('AI_DEFAULT_MODEL', 'openrouter/free'),

    'assistants' => [
        'product-url-extractor' => [
            'provider' => env('AI_URL_EXTRACTOR_PROVIDER', 'openrouter'),
            'model' => env('AI_URL_EXTRACTOR_MODEL', 'openrouter/free'),
            'temperature' => 0.1,
            'max_tokens' => 2048,
        ],
    ],
];
```

### 1.4 Directory Structure

```
app/Services/Ai/
  Assistants/
    ProductUrlAssistant.php      # First assistant: URL → structured product data

app/Models/
  AiExtraction.php               # Audit log model

database/migrations/
  xxxx_xx_xx_create_ai_extractions_table.php

app/Livewire/Products/
  ProductForm.php                # Updated: "Create from URL" button + modal
```

### 1.5 Migration: `ai_extractions` table

```php
Schema::create('ai_extractions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('assistant_name');
    $table->string('source_url');
    $table->text('prompt_data')->nullable();
    $table->json('raw_response')->nullable();
    $table->json('extracted_data')->nullable();
    $table->string('status'); // processing, completed, failed
    $table->text('error_message')->nullable();
    $table->integer('input_tokens')->nullable();
    $table->integer('output_tokens')->nullable();
    $table->timestamps();
});
```

---

## Phase 2: First Assistant — Product URL Extractor

### 2.1 `ProductUrlAssistant`

This assistant takes a supplier product URL, fetches the page, extracts text, sends to Prism (which routes to any configured provider), and returns structured product data.

**Flow:**
```
User pastes URL → Livewire calls assistant →
  1. Validate URL is reachable
  2. Fetch page content via Laravel Http facade (Guzzle)
  3. Strip HTML: remove <script>, <style>, <nav>, <footer>, <header>, <aside>
  4. Extract readable text content
  5. Call Prism::text() with structured output schema
  6. Parse JSON response via Prism's schema validation
  7. Log to ai_extractions table
  8. Return structured data → pre-fill form
```

**Implementation sketch:**
```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

class ProductUrlAssistant
{
    public function extract(string $url, User $user): array
    {
        // 1. Fetch page
        $html = Http::withHeaders(['User-Agent' => '...'])
            ->timeout(15)
            ->get($url)
            ->body();

        // 2. Strip to readable text
        $text = $this->htmlToText($html);

        // 3. Log extraction attempt
        $extraction = AiExtraction::create([
            'user_id' => $user->id,
            'assistant_name' => 'product-url-extractor',
            'source_url' => $url,
            'prompt_data' => $text,
            'status' => 'processing',
        ]);

        try {
            // 4. Call Prism with structured output
            $response = Prism::text()
                ->using(Provider::OpenRouter, config('ai.assistants.product-url-extractor.model'))
                ->withSystemPrompt(view('ai.prompts.product-extraction')->render())
                ->withPrompt($text)
                ->withTemperature(0.1)
                ->withMaxTokens(2048)
                ->withSchema(new ObjectSchema('product', [
                    'name' => new StringSchema('name', 'Product name/title'),
                    'sku' => new StringSchema('sku', 'Product SKU or model number')->nullable(),
                    'description' => new StringSchema('description', 'Brief product description (max 500 chars)')->nullable(),
                    'retail_price' => new NumberSchema('retail_price', 'Customer-facing retail price in GBP')->nullable(),
                    'category_name' => new StringSchema('category_name', 'Best-guess category')->nullable(),
                    'supplier_name' => new StringSchema('supplier_name', 'Supplier/seller name')->nullable(),
                    'supplier_sku' => new StringSchema('supplier_sku', "Supplier's own SKU")->nullable(),
                ]))
                ->asStructured();

            $data = $response->structured->toArray();

            // 5. Log success
            $extraction->update([
                'status' => 'completed',
                'raw_response' => $response->text,
                'extracted_data' => $data,
                'input_tokens' => $response->usage?->inputTokens,
                'output_tokens' => $response->usage?->outputTokens,
            ]);

            return $data;

        } catch (\Throwable $e) {
            $extraction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function htmlToText(string $html): string
    {
        // Strip scripts, styles, nav, footer, header, aside
        // Extract text from visible body content
        // Return cleaned text (max ~8000 chars for LLM context)
    }
}
```

**Extraction system prompt (`resources/views/ai/prompts/product-extraction.blade.php`):**
```blade
You are a product information extraction assistant for a campervan electrical
installation business. Extract product information from the supplier webpage
content below.

Rules:
- Set any unknown field to null
- retail_price should be the customer-facing price in GBP (£), NOT a trade price
- Return ONLY valid JSON matching the requested schema
- Be conservative: only extract what's clearly visible on the page
- The product category should be one of: Solar Panels, Batteries, Chargers,
  Inverters, Cable & Accessories, Monitoring, Fuses & Breakers, Lighting,
  or Other
```

### 2.2 ProductForm Integration

**New properties:**
```php
public bool $showUrlModal = false;
public string $extractionUrl = '';
public ?array $extractedData = null;
public bool $isExtracting = false;
public ?string $extractionError = null;
```

**New methods:**
- `openUrlModal()` — reset state, show modal
- `closeUrlModal()` — hide modal, clear state
- `extractFromUrl()` — validates URL, calls `ProductUrlAssistant`, handles success/error
- `applyExtractedData()` — maps extracted data to form fields + adds supplier link
- `cancelExtraction()` — clear extracted data, keep form as-is

**Modal UI (daisyUI):**
```
┌─────────────────────────────────────────┐
│  Extract Product from URL               │
├─────────────────────────────────────────┤
│                                         │
│  [Step 1: URL Input]                    │
│  ┌─────────────────────────────────┐    │
│  │ https://supplier.com/product/.. │    │
│  └─────────────────────────────────┘    │
│  [Extract]                              │
│                                         │
│  [Step 2: Loading]                      │
│  🌀 Fetching product details...         │
│                                         │
│  [Step 3: Preview ✓]                    │
│  Name:     Victron SmartSolar 100/30    │
│  SKU:      SCC125075210                 │
│  Price:    £199.99                      │
│  Supplier: Bimble Solar                 │
│  Category: Chargers                     │
│                                         │
│  [Apply & Edit]  [Start Over]           │
│                                         │
│  [Step 4: Error]                        │
│  ⚠ Could not access that URL. Check    │
│  it's correct and publicly accessible.  │
│  [Try Again]                            │
└─────────────────────────────────────────┘
```

**Category matching:** Fuzzy-match `category_name` from extracted data against existing `ProductCategory` records. If no match, leave `category_id` unset.

**Supplier matching:** Fuzzy-match `supplier_name` against existing `Supplier` records. If found, pre-fill supplier selection. If not found, leave supplier unselected — user picks from dropdown.

### 2.3 Edge Cases

| Scenario | Handling |
|----------|----------|
| **URL unreachable / 404** | Return clear error: "Could not access that URL. Check it's correct and publicly accessible." |
| **Page requires JavaScript** | Simple HTTP fetch gets no meaningful content → Prism returns sparse data → user sees "Limited data extracted" with what was found |
| **No product found** | Prism returns mostly nulls → show warning: "Could not identify a product on this page" |
| **Multiple prices found** | Prism prompt instructs to pick customer-facing retail price; show extracted price for user verification |
| **Supplier not in system** | Supplier link created without `supplier_id`; user selects from dropdown before saving |
| **Category not found** | Category name shown; user picks from dropdown if no match |
| **Trade price on page** | System prompt explicitly says: extract customer-facing price, not trade/wholesale |
| **Invalid schema from Prism** | Prism's structured output handles this — it enforces schema server-side with some providers |
| **Rate limited / timeout** | Prism throws exception → caught → user-friendly error with retry suggestion |
| **URL not a product page** | Sparse data returned; user can apply partial results or cancel |

---

## Phase 3: Future Expansion

### 3.1 Assistant Registration Pattern

Each new assistant:
1. Create assistant class in `app/Services/Ai/Assistants/`
2. Register in `config/ai.php` under `assistants`
3. Wire into relevant Livewire component

### 3.2 Planned Future Assistants

| Assistant | Description | UI Location |
|-----------|-------------|-------------|
| **Quote summary** | Generate natural-language summary of a quote | QuoteShow page |
| **Product description writer** | Auto-write descriptions from specs | ProductForm |
| **Customer email composer** | Draft email replies from enquiry context | EnquiryForm |
| **Chat widget** | Full chat interface using MCP tools + Prism | Sidebar/standalone |

### 3.3 Chat Widget Architecture (Design Sketch)

```
Livewire ChatWidget
  │
  ├── User message → Prism::text() with MCP tool list as system prompt
  │     (Provider: user-selectable from config — OpenRouter, local models, etc.)
  │
  ├── LLM decides tool call → Laravel MCP Client ($mcpClient->callTool())
  │     (Uses existing QvtServer tools internally)
  │
  └── Response rendered in chat with action buttons (url, confirm/cancel)
```

The chat widget routes through **Prism** for LLM reasoning and tool selection, then calls **MCP tools directly** via `Mcp::client('qvt')` for execution — no duplication of business logic.

---

## Implementation Order

### Phase 1a: Foundation
| # | Task | Details |
|---|------|---------|
| 1 | `composer require prism-php/prism` | Install & publish config |
| 2 | Create `config/ai.php` | Assistant configs + defaults |
| 3 | Create `AiExtraction` model + migration | Audit log table |
| 4 | Create extraction prompt Blade view | `resources/views/ai/prompts/product-extraction.blade.php` |
| 5 | Add env vars to `.env.example` | OpenRouter, Ollama, assistant overrides |
| 6 | Run `pint` | Code style |

### Phase 1b: Product URL Extractor
| # | Task | Details |
|---|------|---------|
| 1 | Create `ProductUrlAssistant` | Fetch → strip → Prism structured output → log |
| 2 | Write tests for `ProductUrlAssistant` | Mock HTTP + fake Prism responses |
| 3 | Update `ProductForm` Livewire | Modal state + extraction methods |
| 4 | Update `product-form.blade.php` | "Create from URL" button + daisyUI modal |
| 5 | Write Livewire feature test | Modal open/close, extraction, apply |
| 6 | Run `pint` | Code style |

### Phase 1c: Edge Cases & Polish
| # | Task | Details |
|---|------|---------|
| 1 | Handle unreachable URLs, JS-only pages, timeouts | User-friendly error messages |
| 2 | Add fuzzy category+supplier matching | Against existing records |
| 3 | Verify trade price safety in extraction | Test that trade prices aren't extracted as retail |
| 4 | Run full test suite | Ensure nothing broken |

---

## Testing Strategy

| Test Type | What It Covers | Approach |
|-----------|---------------|----------|
| **Unit: Assistant** | URL fetch → HTML strip → Prism call → parse | Mock `Http` facade, use `Prism::fake()` for LLM responses |
| **Feature: Extraction flow** | Full extraction with mocked external calls | Mock HTTP + Prism fake, assert `AiExtraction` created |
| **Livewire: Modal UX** | Open/close, extraction trigger, loading state, apply | Standard Livewire test: set properties, call actions, assert state |
| **Edge cases** | Unreachable URL, no product, sparse data | Mock each scenario, assert correct error messages |

**Prism testing utilities make this easy:**
```php
use Prism\Prism\Facades\Prism;

Prism::fake([
    'https://supplier.com/product/...' => Prism::structuredResponse([
        'name' => 'Victron SmartSolar 100/30',
        'sku' => 'SCC125075210',
        'retail_price' => 199.99,
        // ...
    ]),
]);
```

---

## Dependencies

| Package | Purpose |
|---------|---------|
| `prism-php/prism` | LLM abstraction — OpenRouter, Ollama, OpenAI, etc. |
| `laravel/framework` | Bundled `Http` facade for URL fetching |
| `spatie/laravel-permission` | Already installed — admin role gating |

---

## Trade Price Confidentiality

The `ProductUrlAssistant` is designed to extract **retail/customer-facing prices only**:
1. The system prompt explicitly instructs: extract customer-facing price, not trade/wholesale
2. Extracted `retail_price` is mapped to the product's retail price field
3. Trade pricing must always be entered manually by staff (deliberate safety constraint)
4. All extractions are logged in `ai_extractions` for audit

---

*End of plan.*
