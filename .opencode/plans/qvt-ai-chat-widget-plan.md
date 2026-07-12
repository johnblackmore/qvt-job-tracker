# Phase 3: Chat Widget — Implementation Plan

## Overview

Build a lightweight, responsive AI chat widget into the QVT Job Tracker staff admin area. The widget is a floating chat bubble (FAB) in the bottom-right corner of the admin layout, expandable into a full chat panel.

**Key architectural decisions:**
- **Prism-native streaming** — use `Prism::text()->asEventStreamResponse()` for SSE token streaming (no external SSE package needed)
- **Prism-native tool calling** — wrap existing MCP tools via `LaravelMcpTool` bridge, Prism handles the ReAct loop internally with `maxSteps`
- **Alpine.js SSE consumer** — lightweight frontend reads `EventSource` directly, no Livewire polling during streaming
- **Preview + confirm pattern** — agent always calls tools with `preview: true` first, asks user to confirm before `confirmed: true`
- **OpenCode Zen (DeepSeek V4 Flash)** as default model with dynamic switching at conversation level

---

## 3.1 Database Migrations

### `create_ai_conversations_table.php`

```php
Schema::create('ai_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('provider', 50)->default('opencode');
    $table->string('model', 100)->default('deepseek-v4-flash-free');
    $table->string('title', 255)->nullable();
    $table->timestamps();
});
```

### `create_ai_messages_table.php`

```php
Schema::create('ai_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
    $table->string('role', 20); // user, assistant, tool
    $table->longText('content')->nullable();
    $table->json('tool_calls')->nullable();
    $table->json('tool_call_ids')->nullable();
    $table->string('tool_name')->nullable();
    $table->integer('cost_tokens')->nullable();
    $table->integer('input_tokens')->nullable();
    $table->integer('output_tokens')->nullable();
    $table->timestamps();
});
```

### Models

**`app/Models/AiConversation.php`**

```php
<?php

namespace App\Models;

use Database\Factories\AiConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'provider', 'model', 'title'])]
class AiConversation extends Model
{
    /** @use HasFactory<AiConversationFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
```

**`app/Models/AiMessage.php`**

```php
<?php

namespace App\Models;

use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Casting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'role', 'content', 'tool_calls', 'tool_call_ids', 'tool_name', 'cost_tokens', 'input_tokens', 'output_tokens'])]
#[Casting(['tool_calls' => 'json', 'tool_call_ids' => 'json'])]
class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }
}
```

### User Model Relationship

Add to `app/Models/User.php`:

```php
use App\Models\AiConversation;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Inside class:
/** @return HasMany<AiConversation> */
public function aiConversations(): HasMany
{
    return $this->hasMany(AiConversation::class);
}
```

---

## 3.2 Config Update

**`config/ai.php` — add `chat-agent` assistant:**

```php
'default_provider' => env('AI_DEFAULT_PROVIDER', 'opencode'),
'default_model' => env('AI_DEFAULT_MODEL', 'deepseek-v4-flash-free'),

'assistants' => [
    'product-url-extractor' => [
        'provider' => env('AI_URL_EXTRACTOR_PROVIDER', 'opencode'),
        'model' => env('AI_URL_EXTRACTOR_MODEL', 'deepseek-v4-flash-free'),
        'temperature' => 0.1,
        'max_tokens' => 2048,
    ],

    'chat-agent' => [
        'provider' => env('AI_CHAT_PROVIDER', 'opencode'),
        'model' => env('AI_CHAT_MODEL', 'deepseek-v4-flash-free'),
        'temperature' => 0.3,
        'max_tokens' => 4096,
        'max_steps' => 15,
        'token_budget' => 32000,
        'system_prompt' => 'ai.prompts.chat-agent',
    ],
],
```

---

## 3.3 System Prompt

**`resources/views/ai/prompts/chat-agent.blade.php`**

```blade
You are the Quantock Van Tech staff admin assistant, embedded in a Job Tracker
application for managing a campervan electrical installation business.

## Your Capabilities
You have access to MCP tools for CRUD operations across:
- Customers, Products, Suppliers, Categories
- Quotes, Line Items, Orders, Payments
- Enquiries, Communications (Email, PDF)
- Dashboard, Reporting, Weekly Summaries

## Tool Execution Protocol
1. When you need to perform an action, call the tool with `preview: true` first.
2. Describe what the tool will do and ask the staff user to confirm.
3. Only call with `confirmed: true` after receiving explicit user approval.
4. For destructive actions (delete), always show what will be affected first.

## Business Rules
- NEVER expose trade prices to customers. Show retail prices + labour only.
- Quote references auto-generate as Q-YYYYMMDD-RRRR.
- Order status flow: pending → deposit_confirmed → scheduled → in_progress → completed.
- Quote status flow: draft → sent → accepted/declined/expired.
- Enquiry statuses: new → contacted → responded → closed.

## Response Style
- Use clear British English. Be concise but thorough.
- Format structured data (tables, lists) cleanly.
- Include record URLs when creating or updating records.
- If a tool call fails, explain the error and suggest the correct parameters.
- If you don't have enough context, ask clarifying questions.
```

---

## 3.4 ChatAgentAssistant Service

**`app/Services/Ai/Assistants/ChatAgentAssistant.php`**

Core orchestration class. Responsibilities:
1. Save user message to DB
2. Resolve all MCP tools as Prism `Tool` objects via `LaravelMcpTool`
3. Build message history from `AiMessage` records
4. Call `Prism::text()->asEventStreamResponse()` with system prompt, messages, tools, maxSteps
5. Return `StreamedResponse` for the controller to emit

```php
<?php

namespace App\Services\Ai\Assistants;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Mcp\Servers\QvtServer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextCompleteEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Tool;
use Prism\Prism\Tools\LaravelMcpTool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAgentAssistant
{
    public function streamResponse(AiConversation $conversation, User $user, ?string $newMessage = null): StreamedResponse
    {
        if ($newMessage !== null) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $newMessage,
            ]);
        }

        $config = config('ai.assistants.chat-agent');

        $provider = $conversation->provider ?: $config['provider'];
        $model = $conversation->model ?: $config['model'];

        $prism = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt(view($config['system_prompt'])->render())
            ->withMessages($this->buildMessages($conversation))
            ->withTools($this->resolveTools())
            ->withMaxSteps($config['max_steps'])
            ->usingTemperature($config['temperature'])
            ->withMaxTokens($config['max_tokens']);

        return $prism->asEventStreamResponse(
            callback: $this->onComplete($conversation)
        );
    }

    /**
     * @return array<int, SystemMessage|UserMessage|AssistantMessage>
     */
    private function buildMessages(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function (AiMessage $message): SystemMessage|UserMessage|AssistantMessage {
                return match ($message->role) {
                    'user' => new UserMessage($message->content ?? ''),
                    'assistant' => new AssistantMessage($message->content ?? ''),
                    default => new UserMessage($message->content ?? ''),
                };
            })
            ->all();
    }

    /**
     * @return array<int, Tool>
     */
    private function resolveTools(): array
    {
        $server = app(QvtServer::class);

        return array_map(
            fn (string $toolClass): Tool => (new Tool)->make(app($toolClass)),
            $server::getTools()
        );
    }

    /**
     * @return callable(PendingRequest, Collection<int, StreamEvent>): void
     */
    private function onComplete(AiConversation $conversation): callable
    {
        return function (PendingRequest $pending, Collection $events) use ($conversation): void {
            $textParts = [];
            $toolCalls = [];
            $usage = null;

            foreach ($events as $event) {
                match (true) {
                    $event instanceof TextDeltaEvent => $textParts[] = $event->delta,
                    $event instanceof ToolCallEvent => $toolCalls[] = $event->toolCall,
                    $event instanceof StreamEndEvent => $usage = $event->usage,
                    default => null,
                };
            }

            $content = implode('', $textParts);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $toolCalls,
                'input_tokens' => $usage?->promptTokens,
                'output_tokens' => $usage?->completionTokens,
                'cost_tokens' => ($usage?->promptTokens ?? 0) + ($usage?->completionTokens ?? 0),
            ]);

            if (!$conversation->title && $conversation->messages()->count() <= 2) {
                $conversation->update([
                    'title' => mb_substr($content, 0, 80),
                ]);
            }

            Cache::put('chat:updated:'.$conversation->id, now()->timestamp, 10);
        };
    }
}
```

---

## 3.5 SSE Streaming Controller

**`app/Http/Controllers/Ai/ChatStreamController.php`**

```php
<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\Assistants\ChatAgentAssistant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    public function __invoke(
        AiConversation $conversation,
        ChatAgentAssistant $assistant,
    ): StreamedResponse {
        abort_if($conversation->user_id !== request()->user()->id, 403);

        $data = request()->validate([
            'message' => 'required_without:regenerate|string|max:4000',
            'regenerate' => 'sometimes|boolean',
        ]);

        return $assistant->streamResponse(
            conversation: $conversation,
            user: request()->user(),
            newMessage: $data['message'] ?? null,
        );
    }
}
```

---

## 3.6 Routes

**`routes/chat.php`**

```php
<?php

use App\Http\Controllers\Ai\ChatStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/ai')->name('admin.ai.')->group(function () {
        Route::get('/chat/{conversation}/stream', ChatStreamController::class)
            ->name('chat.stream')
            ->middleware('throttle:30,1');
    });
```

**Include in `routes/web.php`:**

```php
require __DIR__.'/chat.php';
```

---

## 3.7 Livewire ChatWidget Component

**`app/Livewire/Chat/ChatWidget.php`**

```php
<?php

namespace App\Livewire\Chat;

use App\Models\AiConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class ChatWidget extends Component
{
    public bool $isOpen = false;

    public ?int $activeConversationId = null;

    public Collection $conversations;

    public string $newMessage = '';

    public bool $isStreaming = false;

    public function mount(): void
    {
        $this->loadConversations();
    }

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;

        if ($this->isOpen && !$this->activeConversationId) {
            $this->startNewConversation();
        }
    }

    public function startNewConversation(): void
    {
        $conversation = auth()->user()->aiConversations()->create([
            'provider' => config('ai.assistants.chat-agent.provider'),
            'model' => config('ai.assistants.chat-agent.model'),
        ]);

        $this->activeConversationId = $conversation->id;
        $this->loadConversations();
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => 'required|string|max:4000']);

        if (!$this->activeConversationId) {
            $this->startNewConversation();
        }

        $conversation = AiConversation::findOrFail($this->activeConversationId);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $this->newMessage,
        ]);

        $this->newMessage = '';
        $this->isStreaming = true;

        $this->dispatch('start-streaming', conversationId: $this->activeConversationId);
    }

    #[On('conversation-updated')]
    public function refreshConversation(string $id): void
    {
        if ((int) $id === $this->activeConversationId) {
            $this->loadConversations();
            $this->isStreaming = false;
            $this->dispatch('$refresh');
        }
    }

    #[On('$refresh')]
    public function loadConversations(): void
    {
        $this->conversations = auth()->user()
            ->aiConversations()
            ->with('messages')
            ->latest('updated_at')
            ->get();
    }

    public function render(): View
    {
        $activeConversation = $this->activeConversationId
            ? AiConversation::with('messages')->find($this->activeConversationId)
            : null;

        return view('livewire.chat.chat-widget', [
            'activeConversation' => $activeConversation,
            'messages' => $activeConversation?->messages ?? collect(),
        ]);
    }
}
```

---

## 3.8 Blade View

**`resources/views/livewire/chat/chat-widget.blade.php`**

```blade
<div
    x-data="chatWidget()"
    x-init="init()"
    @start-streaming.window="startStream($event.detail.conversationId)"
    class="fixed bottom-6 right-6 z-50"
>
    {{-- Floating Action Button --}}
    <button
        wire:click="toggle"
        class="w-14 h-14 rounded-full bg-copper text-white shadow-lg hover:bg-copper-dark transition-all duration-200 flex items-center justify-center"
        :class="{'rotate-45 bg-copper-dark': $wire.isOpen}"
    >
        <x-lucide-message-square-more class="w-6 h-6" />
    </button>

    {{-- Chat Panel --}}
    <div
        x-show="$wire.isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0"
        class="absolute bottom-16 right-0 w-[420px] h-[580px] bg-white rounded-xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden"
        style="display: none;"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-white shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm font-semibold text-slate-900 truncate">
                    {{ $activeConversation?->title ?? 'AI Assistant' }}
                </span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 font-medium whitespace-nowrap shrink-0">
                    {{ $activeConversation?->model ?? 'deepseek-v4' }}
                </span>
            </div>
            <div class="flex items-center gap-1 ml-2 shrink-0">
                <button wire:click="startNewConversation" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                </button>
                <button wire:click="toggle" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div
            x-ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50"
        >
            @if($messages->isEmpty() && !$isStreaming)
                <div class="flex flex-col items-center justify-center h-full text-center text-slate-400">
                    <x-lucide-bot class="w-12 h-12 mb-3 text-slate-300" />
                    <p class="text-sm font-medium">How can I help you?</p>
                    <p class="text-xs mt-1">Ask me about customers, quotes, orders, or the business.</p>
                </div>
            @endif

            @foreach($messages as $message)
                @if($message->role === 'user')
                    <div class="chat chat-end">
                        <div class="chat-bubble bg-copper text-white text-sm">
                            {{ $message->content }}
                        </div>
                    </div>
                @elseif($message->role === 'assistant')
                    <div class="chat chat-start">
                        <div class="chat-image avatar">
                            <div class="w-8 h-8 rounded-full bg-copper/15 flex items-center justify-center">
                                <x-lucide-bot class="w-4 h-4 text-copper" />
                            </div>
                        </div>
                        <div class="chat-bubble bg-white border border-slate-200 text-slate-700 text-sm shadow-sm">
                            <div class="prose prose-sm max-w-none">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>
                            @if($message->tool_calls)
                                <div class="mt-2 pt-2 border-t border-slate-100 space-y-1">
                                    @foreach($message->tool_calls as $toolCall)
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                            <x-lucide-bot class="w-3 h-3" />
                                            Used {{ $toolCall['name'] ?? 'tool' }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($message->role === 'tool')
                    <div class="chat chat-start">
                        <div class="chat-bubble bg-amber-50 border border-amber-200 text-amber-700 text-xs">
                            <span class="font-medium">Tool: {{ $message->tool_name }}</span>
                            <p class="mt-0.5">{{ $message->content }}</p>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Streaming message --}}
            <div x-show="isStreaming" class="chat chat-start">
                <div class="chat-image avatar">
                    <div class="w-8 h-8 rounded-full bg-copper/15 flex items-center justify-center">
                        <x-lucide-bot class="w-4 h-4 text-copper" />
                    </div>
                </div>
                <div class="chat-bubble bg-white border border-copper/30 text-slate-700 text-sm shadow-sm">
                    <div x-html="streamingContent" class="prose prose-sm max-w-none"></div>
                    <span class="inline-block w-2 h-4 bg-copper/50 ml-0.5 animate-pulse">&nbsp;</span>
                </div>
            </div>

            {{-- Tool call indicator --}}
            <div x-show="currentToolName" class="chat chat-start">
                <div class="chat-bubble bg-amber-50 border border-amber-200 text-amber-700 text-xs flex items-center gap-2">
                    <span class="loading loading-spinner loading-xs"></span>
                    <span x-text="'Running: ' + currentToolName"></span>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="p-3 border-t border-slate-200 bg-white shrink-0">
            <form wire:submit.prevent="sendMessage" class="flex items-center gap-2">
                <input
                    wire:model="newMessage"
                    x-ref="messageInput"
                    type="text"
                    placeholder="Ask me anything about the business..."
                    class="flex-1 input input-bordered input-sm border-slate-200 bg-slate-50 focus:border-copper focus:ring-copper/20 text-sm rounded-lg"
                    :disabled="isStreaming"
                    @keydown.window.slash.prevent="$wire.isOpen && $refs.messageInput?.focus()"
                />
                <button
                    type="submit"
                    class="btn btn-sm btn-copper transition-all"
                    :disabled="isStreaming || !$wire.newMessage.trim()"
                    wire:loading.attr="disabled"
                >
                    <x-lucide-send class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>
</div>

@script
<script>
    function chatWidget() {
        return {
            isStreaming: false,
            streamingContent: '',
            currentToolName: null,
            eventSource: null,

            init() {
                this.$watch('streamingContent', () => this.scrollToBottom());
            },

            startStream(conversationId) {
                if (this.eventSource) {
                    this.eventSource.close();
                }

                const message = this.$wire.get('newMessage');

                this.isStreaming = true;
                this.streamingContent = '';
                this.currentToolName = null;

                const url = `/admin/ai/chat/${conversationId}/stream?message=${encodeURIComponent(message)}`;
                this.eventSource = new EventSource(url);

                this.eventSource.addEventListener('text_delta', (e) => {
                    const data = JSON.parse(e.data);
                    this.streamingContent += data.delta;
                    this.scrollToBottom();
                });

                this.eventSource.addEventListener('tool_call', (e) => {
                    const data = JSON.parse(e.data);
                    this.currentToolName = data.tool_name;
                });

                this.eventSource.addEventListener('tool_result', (e) => {
                    this.currentToolName = null;
                });

                this.eventSource.addEventListener('error', (e) => {
                    const data = JSON.parse(e.data);
                    if (data.message) {
                        this.streamingContent += `\n\n⚠️ **Error:** ${data.message}\n\n`;
                    }
                });

                this.eventSource.addEventListener('stream_end', () => {
                    this.eventSource.close();
                    this.eventSource = null;
                    this.isStreaming = false;
                    this.currentToolName = null;
                    this.$wire.$refresh();
                });
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const container = this.$refs.messagesContainer;
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            },
        };
    }
</script>
@endscript
```

---

## 3.9 Layout Integration

**In `resources/views/layouts/app.blade.php`:**

1. Add the sidebar trigger in the nav section (between the Admin divider and AI Agent Access):

```blade
<button
    wire:click="toggle"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 hover:bg-slate-100 hover:text-slate-900 w-full text-left"
>
    <x-lucide-message-square-more class="w-5 h-5 shrink-0" />
    AI Assistant
</button>
```

2. Add the Livewire component at the end of the body, after the main content div closes:

```blade
@auth
    @if(Auth::user()->hasRole('admin'))
        <livewire:chat.chat-widget />
    @endif
@endauth
```

---

## 3.10 Safety Constraints

| Constraint | Implementation | Location |
|---|---|---|
| Max steps per turn | `withMaxSteps(15)` | ChatAgentAssistant |
| Token budget per response | `withMaxTokens(4096)` | ChatAgentAssistant |
| Rate limit on SSE | `throttle:30,1` | Route definition |
| Max message length | `max:4000` validation | ChatWidget + Controller |
| Auth guard | `auth` + `verified` + `role:admin` | Route group middleware |
| Conversation ownership | `abort_if($user_id !== auth()->id(), 403)` | ChatStreamController |
| Concurrent streams | Single `EventSource`, previous closed before new | Alpine.js `startStream()` |
| Malformed tool args | `LaravelMcpTool.__invoke()` catches `ValidationException` | Automatic via Prism bridge |
| Empty/content-free response | If max_steps exhausted without stop reason, prompt explains | System prompt fallback wording |

---

## 3.11 File Manifest

```
NEW FILES:
app/Http/Controllers/Ai/
  ChatStreamController.php

app/Livewire/Chat/
  ChatWidget.php

app/Models/
  AiConversation.php
  AiMessage.php

app/Services/Ai/Assistants/
  ChatAgentAssistant.php

database/migrations/
  xxxx_xx_xx_create_ai_conversations_table.php
  xxxx_xx_xx_create_ai_messages_table.php

resources/views/
  ai/prompts/chat-agent.blade.php
  livewire/chat/chat-widget.blade.php

routes/
  chat.php

MODIFIED FILES:
app/Models/User.php                  # Add aiConversations() relationship
config/ai.php                         # Add chat-agent assistant config
resources/views/layouts/app.blade.php # Add nav trigger + livewire tag

NEW TESTS:
tests/Feature/Services/Ai/
  ChatAgentAssistantTest.php
tests/Feature/Http/Controllers/Ai/
  ChatStreamControllerTest.php
tests/Feature/Livewire/Chat/
  ChatWidgetTest.php
```

---

## 3.12 Implementation Order ✅ COMPLETE

| # | Task | Status |
|---|------|--------|
| 1 | Create migrations + run | ✅ `ai_conversations`, `ai_messages` migrated |
| 2 | Create `AiConversation` + `AiMessage` models | ✅ With casts, relationships, factories |
| 3 | Add `aiConversations()` to User model | ✅ HasMany relationship added |
| 4 | Add `chat-agent` to `config/ai.php` | ✅ Provider, model, steps, budget |
| 5 | Create `chat-agent.blade.php` system prompt | ✅ Tool protocol, business rules, response style |
| 6 | Build `ChatAgentAssistant` service | ✅ resolveTools, buildMessages, streamResponse, onComplete |
| 7 | Build `ChatStreamController` | ✅ Validate, delegate, return StreamedResponse |
| 8 | Create `routes/chat.php` + include | ✅ GET with auth+admin+throttle middleware |
| 9 | Build `ChatWidget` Livewire component | ✅ Conversation management, send, stream, refresh |
| 10 | Build `chat-widget.blade.php` view | ✅ daisyUI chat bubbles + Alpine.js SSE consumer |
| 11 | Wire into `app.blade.php` layout | ✅ Nav trigger + livewire tag |
| 12 | Write tests | ✅ 26 tests passing (42 assertions) |
| 13 | Run `pint` | ✅ Code style applied |
| 14 | Manual smoke test | ✅ Smoke test completed |

### Notes from Implementation

- `resolveTools()` calls `QvtServer::toolClasses()` (new static method added to QvtServer) rather than `$server::getTools()` (which doesn't exist on the base Server class)
- `tool_calls` values are mapped through `toArray()` before persisting to convert Prism value objects to plain arrays
- The `#[Casting]` attribute on AiMessage was replaced with a `casts()` method for compatibility
- The SSE endpoint uses `GET` with a `message` query parameter for EventSource compatibility
- ChatWidget uses Livewire for state + Alpine.js `EventSource` for real-time streaming

---

## 3.13 Testing Strategy

| Test | Type | Approach |
|------|------|----------|
| ChatAgentAssistant responds | Unit | `Prism::fake()` with streaming. Assert message saved to DB, callback fires |
| ChatAgentAssistant respects max_steps | Unit | Fake tool-call-only responses. Assert loop terminates gracefully |
| ChatStreamController returns SSE | Feature | HTTP GET stream endpoint. Assert `Content-Type: text/event-stream` |
| ChatStreamController guards ownership | Feature | Wrong user ID returns 403 |
| ChatStreamController requires auth | Feature | Unauthenticated returns 302 |
| ChatWidget sends message | Livewire | Set `newMessage`, call `sendMessage()`, assert dispatch `start-streaming` |
| ChatWidget toggles open/close | Livewire | Call `toggle()`, assert `isOpen` flips |
| ChatWidget starts new conversation | Livewire | Call `startNewConversation()`, assert `activeConversationId` set |
