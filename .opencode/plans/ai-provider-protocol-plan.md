# AI Provider Protocol & Model Capabilities Plan

**Status:** Draft — ready for review  
**Date:** 2026-07-18  
**Author:** AI Planning Agent

---

## 1. Problem Statement

Prism supports multiple AI provider protocols (OpenAI-compatible `/chat/completions`, Anthropic `/messages`, Google Gemini, etc.) via dedicated provider classes. However, the same base endpoint (e.g. `opencode-go`, `opencode`) can host models using *different* protocols — for example, `deepseek-v4-flash-free` uses OpenAI-compatible format, while `qwen3.7-plus` uses Anthropic format.

Currently all `opencode-go` and `opencode` models are hardcoded to use the `DeepSeek` provider class (OpenAI-compatible). There is no way to store which protocol a model uses, and no way to tell Prism which provider class to instantiate at runtime.

Additionally, the model config only has a `has_vision` toggle. Other capabilities (`supports_text`, `supports_audio`, `supports_file_uploads`) are not tracked, limiting flexibility when building future AI assistants.

---

## 2. Solution

### 2.1 API Protocol Routing

Add an `api_type` field to `AiModelConfig` (values: `null`/`openai_compatible`/`openai`/`anthropic`/`google`). Register compound provider names in Prism (e.g. `opencode-anthropic`) mapped to the correct Prism provider class. Add a `resolvedProvider()` accessor on the model that returns the correct compound name based on `provider + api_type`.

Each assistant service changes one line — from `$configRecord?->provider` to `$configRecord?->resolvedProvider()` — to let the model own the routing logic.

### 2.2 Capability Toggles

Add `supports_text` (default true), `supports_audio` (default false), `supports_file_uploads` (default false) alongside existing `has_vision`. These are informational/filtering flags only — they don't affect Prism routing.

---

## 3. Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/2026_07_18_XXXXXX_add_api_type_and_capabilities_to_ai_model_configs.php` | Add `api_type`, `supports_text`, `supports_audio`, `supports_file_uploads` columns |

---

## 4. Files to Modify

### 4.1 Infrastructure — Prism Registration

| File | Change |
|------|--------|
| `config/prism.php` | Add 6 new provider entries: `opencode-openai`, `opencode-openai-compatible`, `opencode-anthropic`, `opencode-google`, `opencode-go-anthropic` (all same URL/keys as their base provider) |
| `app/Providers/AppServiceProvider.php` | Register each new provider name with its correct Prism class via `PrismManager::extend()` — `OpenAI::class` for `-openai`, `DeepSeek::class` for `-openai-compatible`, `Anthropic::class` for `-anthropic`, `Gemini::class` for `-google` |

### 4.2 Model Layer

| File | Change |
|------|--------|
| `app/Models/AiModelConfig.php` | Add `api_type`, `supports_text`, `supports_audio`, `supports_file_uploads` to `$fillable`. Add `resolvedProvider(): string` accessor. Add `$casts` for booleans. |

### 4.3 Assistant Services (4 files, 1 line each)

| File | Change |
|------|--------|
| `app/Services/Ai/Assistants/ProductUrlAssistant.php` | Line ~27: `$provider = $configRecord?->resolvedProvider() ?? $fallback['provider']` |
| `app/Services/Ai/Assistants/ExpensesExtractorAssistant.php` | Line ~37: same change |
| `app/Services/Ai/Assistants/ChatAgentAssistant.php` | Line ~47: same change |
| `app/Services/EnquiryAiAssistantService.php` | Line ~35: same change |

### 4.4 Form & UI

| File | Change |
|------|--------|
| `app/Livewire/AiModelConfigs/AiModelConfigForm.php` | Add `$api_type`, `$supports_text`, `$supports_audio`, `$supports_file_uploads` properties. Add validation. Mount existing values. |
| `resources/views/livewire/ai-model-configs/ai-model-config-form.blade.php` | Add `api_type` select field. Add 3 capability checkboxes. |

### Files NOT Changed

- `AiAssistantConfigSettings` (settings model + Livewire + view)
- `AiModelConfigList` + view
- All expense Livewire components
- Any env vars

---

## 5. ResolvedProvider Accessor Logic

```php
public function resolvedProvider(): string
{
    $apiType = $this->api_type ?? 'openai_compatible';

    if ($apiType === 'openai_compatible') {
        return $this->provider;
    }

    $typeSuffix = match ($apiType) {
        'openai' => '-openai',
        'anthropic' => '-anthropic',
        'google' => '-google',
    };

    return $this->provider . $typeSuffix;
}
```

### Mappings Produced

| Stored `provider` | Stored `api_type` | `resolvedProvider()` | Prism class |
|---|---|---|---|
| `opencode` | `null` / `openai_compatible` | `opencode` | `DeepSeek` |
| `opencode` | `openai` | `opencode-openai` | `OpenAI` |
| `opencode` | `anthropic` | `opencode-anthropic` | `Anthropic` |
| `opencode` | `google` | `opencode-google` | `Gemini` |
| `opencode-go` | `null` / `openai_compatible` | `opencode-go` | `DeepSeek` |
| `opencode-go` | `anthropic` | `opencode-go-anthropic` | `Anthropic` |
| `openai` | any | `openai` | `OpenAI` (built-in) |
| `anthropic` | any | `anthropic` | `Anthropic` (built-in) |

---

## 6. Example Config Setup

Once built, an admin creates these `AiModelConfig` records:

| Label | Provider | Model | api_type | Vision | Text |
|-------|----------|-------|----------|--------|------|
| OpenCode DeepSeek Flash Free | `opencode-go` | `deepseek-v4-flash-free` | *(null)* | No | Yes |
| OpenCode DeepSeek Flash Free (Vision) | `opencode-go` | `deepseek-v4-flash-free` | *(null)* | Yes | Yes |
| OpenCode Qwen 3.7 Plus | `opencode-go` | `qwen3.7-plus` | `anthropic` | Yes | Yes |
| OpenCode GPT-4o | `opencode` | `gpt-4o` | `openai` | Yes | Yes |
| OpenCode Gemini 2.5 Pro | `opencode` | `gemini-2.5-pro` | `google` | Yes | Yes |

Then assign them to assistants via AI Assistants → Settings as before.

---

## 7. Rollout / Backward Compatibility

- Existing records get `api_type = null` → resolves to existing behaviour (OpenAI-compatible)
- Existing records get `supports_text = true`, `supports_audio = false`, `supports_file_uploads = false`
- No breakage. No need to update existing configs.

---

## 8. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Missing provider registration for a compound name | 500 error when calling that model | Must verify each compound name is registered in both `config/prism.php` and `AppServiceProvider` |
| Misconfigured `api_type` (e.g. `anthropic` on a true OpenAI model) | API errors from mismatched request format | The error comes from the API immediately. Staff can fix by changing the `api_type` dropdown |
| Forgetting to use `resolvedProvider()` in a new service | Service uses wrong protocol | Code review. The 4 existing services are the only ones using `Prism::using()` |
