# AI Assistant Stats & Logs — Implementation Plan

## Navigation Changes

- Sidebar "AI Assistants" link changes from `route('admin.ai.assistant-settings')` → `route('admin.ai.assistants.index')`
- Settings page (model assignment) removed from sidebar; accessible via gear icon on the overview page
- Route matching updated to `admin.ai.assistants.*`

## Pricing Fields on AiModelConfig

Add two decimal columns to `ai_model_configs`:

| Column | Type | Description |
|--------|------|-------------|
| `input_price` | `decimal(10,4)` nullable | USD cost per 1M input tokens |
| `output_price` | `decimal(10,4)` nullable | USD cost per 1M output tokens |

### Cost Calculation

```
estimated_cost = (input_tokens / 1_000_000 * input_price)
               + (output_tokens / 1_000_000 * output_price)
```

Fuzzy-match by `provider` + `model`. Display to **4 decimal places**.

## New Routes (`routes/ai-assistants.php`)

| URI | Component | Purpose |
|-----|-----------|---------|
| `/admin/ai/assistants` | `AiAssistantsIndex` | Overview — 3 clickable cards |
| `/admin/ai/assistants/chat-agent` | `ChatAgentDetail` | Chat Agent stats + conversation list |
| `/admin/ai/assistants/product-extractor` | `ProductExtractorDetail` | Product Extractor stats + extraction list |
| `/admin/ai/assistants/enquiry-draft` | `EnquiryDraftDetail` | Enquiry Draft stats + draft list |

## Livewire Components

### 1. `AiAssistantsIndex` — Overview Dashboard

Three large stat cards with "View Details" links. Platform summary row below. Gear icon in header links to settings.

### 2. `ChatAgentDetail` — Conversations + Stats

Top stat bar (4 cards), Provider/Model breakdown table, Staff usage table, date-filtered conversation list with view modal (chat bubbles).

### 3. `ProductExtractorDetail` — Extractions + Stats

Top stat bar (4 cards), Provider/Model breakdown, date-filtered extraction list with view modal (extracted data + expandable prompt).

### 4. `EnquiryDraftDetail` — Drafts + Stats

Top stat bar (4 cards), Provider/Model breakdown, date-filtered draft list with view modal (email preview + expandable prompt).

## Files to Create (10)

1. `database/migrations/XXXX_XX_XX_XXXXXX_add_pricing_to_ai_model_configs.php`
2. `routes/ai-assistants.php`
3. `app/Livewire/AiAssistants/AiAssistantsIndex.php`
4. `resources/views/livewire/ai-assistants/ai-assistants-index.blade.php`
5. `app/Livewire/AiAssistants/ChatAgentDetail.php`
6. `resources/views/livewire/ai-assistants/chat-agent-detail.blade.php`
7. `app/Livewire/AiAssistants/ProductExtractorDetail.php`
8. `resources/views/livewire/ai-assistants/product-extractor-detail.blade.php`
9. `app/Livewire/AiAssistants/EnquiryDraftDetail.php`
10. `resources/views/livewire/ai-assistants/enquiry-draft-detail.blade.php`

## Files to Modify (5)

1. `routes/web.php` — Add `require __DIR__.'/ai-assistants.php';`
2. `resources/views/layouts/app.blade.php` — Update nav link + route matching
3. `app/Models/AiModelConfig.php` — Add `input_price`, `output_price` to `#[Fillable]`
4. `app/Livewire/AiModelConfigs/AiModelConfigForm.php` — Add pricing fields
5. `resources/views/livewire/ai-model-configs/ai-model-config-form.blade.php` — Add pricing inputs
