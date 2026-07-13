# AI Model Configuration — Implementation Plan

## Overview

Build a two-part feature to manage AI provider/model pairings and assign them to individual assistants.

- **Part 1** — CRUD for named provider/model pairings stored as Eloquent `AiModelConfig` records.
- **Part 2** — Map those pairings to individual assistants (`chat-agent`, `product-url-extractor`) via `spatie/laravel-settings`.

**Key architectural decisions:**
- **`AiModelConfig` Eloquent model** — stores provider/model pairings in a DB table
- **`spatie/laravel-settings`** — stores which pairing is assigned to which assistant
- **Provider list sourced from `config('prism.providers')`** — dropdown of available providers
- **Model is free text** — user types the model name
- **API keys stay in `.env`** — no keys stored in DB; the pairing only selects which provider + model to use
- **Fallback to `config/ai.php`** — if no pairing is assigned, use the env-based defaults
- **`label` is not unique** — user might want "GPT-4o Backup" entries

---

## 1. Install & Configure spatie/laravel-settings

```bash
composer require spatie/laravel-settings
php artisan vendor:publish --provider="Spatie\LaravelSettings\SettingsServiceProvider" --tag="migrations"
php artisan migrate
```

Enable auto-discovery for settings classes (default scans `app/Settings/`).

---

## 2. Database Migration: `create_ai_model_configs_table`

```php
Schema::create('ai_model_configs', function (Blueprint $table) {
    $table->id();
    $table->string('label');              // e.g. "OpenCode DeepSeek Flash Free"
    $table->string('provider', 50);       // e.g. "opencode"
    $table->string('model', 100);         // e.g. "deepseek-v4-flash-free"
    $table->text('description')->nullable();
    $table->timestamps();
});
```

No unique constraint on `label`. `provider` and `model` are validated at form level.

---

## 3. Model: `AiModelConfig`

```php
<?php

namespace App\Models;

use Database\Factories\AiModelConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'provider', 'model', 'description'])]
class AiModelConfig extends Model
{
    /** @use HasFactory<AiModelConfigFactory> */
    use HasFactory;
}
```

Factory with sensible defaults (`opencode` / `deepseek-v4-flash-free`).

---

## 4. Settings Class: `AiAssistantConfigSettings`

Settings group: `ai_assistant_config`

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AiAssistantConfigSettings extends Settings
{
    public ?int $chat_agent_config_id = null;
    public ?int $product_url_extractor_config_id = null;

    public static function group(): string
    {
        return 'ai_assistant_config';
    }
}
```

Generate via `php artisan make:setting AiAssistantConfigSettings`.

Settings migration (in `database/settings/`):

```php
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai_assistant_config.chat_agent_config_id', null);
        $this->migrator->add('ai_assistant_config.product_url_extractor_config_id', null);
    }
};
```

Run: `php artisan make:settings-migration CreateAiAssistantConfigSettings`

---

## 5. Livewire Components

### 5.1 `AiModelConfigList`

**Route:** `GET /admin/ai/configs`

- Table with columns: label, provider badge, model (monospace), description snippet
- Actions per row:
  - **Edit** → `route('admin.ai.configs.edit', $config)`
  - **Delete** → Livewire confirm flow
    - Warn if this config is assigned to any assistant (checks settings)
    - If assigned, settings field becomes `null` after deletion (graceful)
  - **Assign to** dropdown → quick-assign to `chat-agent` or `product-url-extractor`
- Empty state: "No model configs yet. Create your first pairing to get started."
- **New** button → `route('admin.ai.configs.create')`

### 5.2 `AiModelConfigForm`

**Routes:** `GET /admin/ai/configs/create`, `GET /admin/ai/configs/{id}/edit`

Form fields:
- `label` — text input, required, max 255
- `provider` — select dropdown, required, options = `array_keys(config('prism.providers'))`
- `model` — text input, required, max 100
- `description` — textarea, nullable, max 500

Validation rules:
```php
[
    'label' => 'required|string|max:255',
    'provider' => 'required|string|in:' . implode(',', array_keys(config('prism.providers'))),
    'model' => 'required|string|max:100',
    'description' => 'nullable|string|max:500',
]
```

Success flash: "AI model config created." / "AI model config updated."

### 5.3 `AiAssistantConfigSettings`

**Route:** `GET /admin/ai/assistant-settings`

Shows each known assistant type:
- **Chat Agent** (`chat-agent`) — powers the staff chat widget
  - Dropdown: [None — use env defaults] + all `AiModelConfig` records
- **Product URL Extractor** (`product-url-extractor`) — extracts product data from URLs
  - Dropdown: [None — use env defaults] + all `AiModelConfig` records

Save button persists to `AiAssistantConfigSettings`.

---

## 6. Routes

New file: `routes/ai-configs.php`

```php
<?php

use App\Livewire\AiModelConfigs\AiAssistantConfigSettings;
use App\Livewire\AiModelConfigs\AiModelConfigForm;
use App\Livewire\AiModelConfigs\AiModelConfigList;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/ai')
    ->name('admin.ai.')
    ->group(function () {
        Route::get('/configs', AiModelConfigList::class)->name('configs.index');
        Route::get('/configs/create', AiModelConfigForm::class)->name('configs.create');
        Route::get('/configs/{aiModelConfig}/edit', AiModelConfigForm::class)->name('configs.edit');
        Route::get('/assistant-settings', AiAssistantConfigSettings::class)->name('assistant-settings');
    });
```

Include in `routes/web.php`:

```php
require __DIR__.'/ai-configs.php';
```

---

## 7. Navigation

Add two links to the admin sidebar in `resources/views/layouts/app.blade.php` (between "AI Assistant" and "AI Agent Access"):

```blade
{{-- AI Config --}}
<a href="{{ route('admin.ai.configs.index') }}"
   class="... {{ request()->routeIs('admin.ai.configs.*') ? 'bg-copper-50 text-copper-700' : '' }} ...">
    <x-lucide-cpu class="w-5 h-5 shrink-0" />
    AI Config
</a>

{{-- Assistant Settings --}}
<a href="{{ route('admin.ai.assistant-settings') }}"
   class="... {{ request()->routeIs('admin.ai.assistant-settings') ? 'bg-copper-50 text-copper-700' : '' }} ...">
    <x-lucide-sliders-horizontal class="w-5 h-5 shrink-0" />
    Assistant Settings
</a>
```

---

## 8. Integration: Modify Existing Assistant Classes

### `ChatAgentAssistant.php` (currently reads `config('ai.assistants.chat-agent')`)

Replace the config resolution with a helper method:

```php
use App\Models\AiModelConfig;
use App\Settings\AiAssistantConfigSettings;

// Inside streamResponse():
$settings = app(AiAssistantConfigSettings::class);
$configRecord = $settings->chat_agent_config_id
    ? AiModelConfig::find($settings->chat_agent_config_id)
    : null;
$fallback = config('ai.assistants.chat-agent');

$provider = $configRecord?->provider ?? $fallback['provider'];
$model = $configRecord?->model ?? $fallback['model'];
```

The rest of the Prism builder stays the same (system prompt, max_steps, temperature, etc.).

### `ProductUrlAssistant.php`

Same pattern but with `product_url_extractor_config_id`:

```php
$configRecord = $settings->product_url_extractor_config_id
    ? AiModelConfig::find($settings->product_url_extractor_config_id)
    : null;
```

### `ChatWidget.php` (line ~44-45)

When creating a new conversation, resolve the active provider/model from settings:

```php
$settings = app(AiAssistantConfigSettings::class);
$configRecord = $settings->chat_agent_config_id
    ? AiModelConfig::find($settings->chat_agent_config_id)
    : null;

$conversation = auth()->user()->aiConversations()->create([
    'provider' => $configRecord?->provider ?? config('ai.assistants.chat-agent.provider'),
    'model' => $configRecord?->model ?? config('ai.assistants.chat-agent.model'),
]);
```

---

## 9. MCP Tools

Following AGENTS.md rule #1 (New Feature = New Tool), create in `app/Mcp/Tools/AiConfig/`:

| Tool | Purpose | Params |
|------|---------|--------|
| `ListAiModelConfigsTool` | List all pairings | none |
| `GetAiModelConfigTool` | Get single pairing | `id` |
| `CreateAiModelConfigTool` | Create pairing | `label`, `provider`, `model`, `description?` |
| `UpdateAiModelConfigTool` | Update pairing | `id`, `label?`, `provider?`, `model?`, `description?` |
| `DeleteAiModelConfigTool` | Delete pairing | `id`, `preview`/`confirmed` |
| `GetAiAssistantConfigSettingsTool` | Read current assignments | none |
| `UpdateAiAssistantConfigSettingsTool` | Update assignments | `chat_agent_config_id?`, `product_url_extractor_config_id?` |

All tools return:
- `message` field with natural language
- `url` field using `route()` for single records
- List responses include `url` on each item

Register all tool classes in `QvtServer::$tools` array.

---

## 10. Edge Cases & Safety

| Concern | Handling |
|---------|----------|
| No configs exist yet | List shows helpful empty state. Settings page shows empty dropdowns + note to create configs first. Fallback to env defaults. |
| Config deleted while assigned | Settings field becomes `null`; assistants fall back to env defaults. Delete confirmation warns if config is in use. |
| Invalid config ID in settings | `AiModelConfig::find()` returns `null`; gracefully falls back to env defaults |
| Provider removed from `prism.php` | Config still valid as stored string. Prism will error at runtime. UI shows warning badge if provider key not found in `prism.providers`. |
| Two providers share same API key env var | `opencode` and `opencode-go` both use `OPENCODE_API_KEY` — works fine, the pairing just selects which provider Prism uses |

---

## 11. File Manifest

```
NEW FILES:
  app/Livewire/AiModelConfigs/
    AiModelConfigList.php
    AiModelConfigForm.php
    AiAssistantConfigSettings.php

  app/Models/
    AiModelConfig.php

  app/Settings/
    AiAssistantConfigSettings.php

  app/Mcp/Tools/AiConfig/
    ListAiModelConfigsTool.php
    GetAiModelConfigTool.php
    CreateAiModelConfigTool.php
    UpdateAiModelConfigTool.php
    DeleteAiModelConfigTool.php
    GetAiAssistantConfigSettingsTool.php
    UpdateAiAssistantConfigSettingsTool.php

  database/migrations/
    xxxx_xx_xx_create_ai_model_configs_table.php

  database/settings/
    xxxx_xx_xx_create_ai_assistant_config_settings.php

  database/factories/
    AiModelConfigFactory.php

  resources/views/livewire/ai-model-configs/
    ai-model-config-list.blade.php
    ai-model-config-form.blade.php
    ai-assistant-config-settings.blade.php

  routes/
    ai-configs.php

MODIFIED FILES:
  app/Services/Ai/Assistants/ChatAgentAssistant.php
  app/Services/Ai/Assistants/ProductUrlAssistant.php
  app/Livewire/Chat/ChatWidget.php
  app/Mcp/Servers/QvtServer.php
  resources/views/layouts/app.blade.php
  routes/web.php
```

---

## 12. Implementation Order ✅ COMPLETE

| # | Task | Status | Notes |
|---|------|--------|-------|
| 1 | Install spatie/laravel-settings, publish config, run base migration | ✅ | v3.9.0 installed. No publishable assets — copied migration stub manually. |
| 2 | Create `AiModelConfig` migration + model + factory | ✅ | `label`, `provider`, `model`, `description` fields |
| 3 | Create `AiAssistantConfigSettings` class + settings migration | ✅ | Group: `ai_assistant_config`, properties: `chat_agent_config_id`, `product_url_extractor_config_id` |
| 4 | Build `AiModelConfigForm` Livewire component + view | ✅ | Provider dropdown from `config('prism.providers')` keys |
| 5 | Build `AiModelConfigList` Livewire component + view | ✅ | Table with assign dropdown, delete with assignment warning |
| 6 | Build `AiAssistantConfigSettings` Livewire component + view | ✅ | Per-assistant dropdowns with "None — use env defaults" option |
| 7 | Route file + sidebar nav links | ✅ | Routes in `routes/ai-configs.php`, included in `routes/web.php`. Nav link added between AI Assistant and AI Agent Access. |
| 8 | Modify `ChatAgentAssistant` | ✅ | Resolves from settings → DB → config fallback |
| 9 | Modify `ProductUrlAssistant` | ✅ | Same pattern with `product_url_extractor_config_id` |
| 10 | Modify `ChatWidget` | ✅ | Store resolved provider/model on new conversation |
| 11 | Create MCP tools | ✅ | 7 tools in `App\Mcp\Tools\AiConfig\` namespace, preview/confirmed pattern |
| 12 | Register tools in `QvtServer` | ✅ | Added to both `$tools` array and `toolClasses()` static method |
| 13 | Write tests | ✅ | 28 tests (96 assertions): 14 Livewire + 14 MCP tool tests |
| 14 | Run `pint` | ✅ | Code style applied |
| 15 | Update AGENTS.md & MCP server plan | ✅ | Plan updated, AGENTS.md updated |

### Notes from Implementation

- The manual migration stub copy was needed because `php artisan vendor:publish --provider="Spatie\LaravelSettings\SettingsServiceProvider"` produced no output in v3.9.0.
- The @livewire('actions.logout') in the layout references a Volt component that doesn't exist as a standalone class. This is a pre-existing concern (not introduced by this feature).
- MCP tool test assertions use `->etc()` on `assertStructuredContent()` callbacks to allow unasserted keys in responses.
- The `AiAssistantConfigSettings` Livewire component handles null/unset via empty string value in the HTML select.

---

## 13. Testing Strategy

| Test | Type | Approach |
|------|------|----------|
| AiModelConfigList renders empty state | Feature | Mount component, assert "No model configs yet" |
| AiModelConfigList shows configs | Feature | Create records, mount, assert visible |
| AiModelConfigForm creates config | Feature | Fill form, submit, assert DB record |
| AiModelConfigForm validates provider | Feature | Submit invalid provider, assert error |
| Delete config with assignment | Feature | Assign to assistant, delete, assert settings = null |
| AiAssistantConfigSettings persists | Feature | Select config, save, assert settings stored |
| ChatAgentAssistant uses DB config | Unit | Mock settings + model, assert Prism gets correct provider/model |
| ProductUrlAssistant falls back | Unit | Null settings, assert fallback to config('ai') |
| MCP tool CRUD | Feature | Assert preview + confirmed patterns |
| Unauthenticated access | Feature | Guest hits route, assert redirect |
| Installer role access | Feature | Installer hits route, assert 403 |
