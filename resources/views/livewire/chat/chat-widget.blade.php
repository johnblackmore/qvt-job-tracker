<div
    x-data="{
        isOpen: $wire.$entangle('isOpen', true),
        isStreaming: false,
        streamingContent: '',
        currentToolName: null,
        currentConversationId: null,
        eventSource: null,
        showConversations: false,

        init() {
            this.$watch('streamingContent', () => this.scrollToBottom());
        },

        startStream(conversationId) {
            if (this.eventSource) {
                this.eventSource.close();
            }

            this.currentConversationId = conversationId;
            this.isStreaming = true;
            this.streamingContent = '';
            this.currentToolName = null;

            this.eventSource = new EventSource(`/admin/ai/chat/${conversationId}/stream`);

            this.eventSource.addEventListener('text_delta', (e) => {
                const data = JSON.parse(e.data);
                this.streamingContent += data.delta;
                this.scrollToBottom();
            });

            this.eventSource.addEventListener('tool_call', (e) => {
                const data = JSON.parse(e.data);
                this.currentToolName = data.tool_name;
                this.scrollToBottom();
            });

            this.eventSource.addEventListener('tool_result', () => {
                this.currentToolName = null;
            });

            this.eventSource.addEventListener('error', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.message) {
                        this.streamingContent += `\n\n**Error:** ${data.message}\n\n`;
                    }
                } catch {
                    // Non-JSON error events from EventSource
                }
                this.scrollToBottom();
            });

            this.eventSource.addEventListener('stream_end', () => {
                this.cleanupStream();
            });

            this.eventSource.onerror = () => {
                this.cleanupStream();
            };
        },

        cleanupStream() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            this.isStreaming = false;
            this.currentToolName = null;
            this.currentConversationId = null;

            $wire.set('isStreaming', false);
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
    }"
    @start-streaming.window="startStream($event.detail.conversationId)"
    @chat-opened.window="scrollToBottom()"
    class="fixed bottom-6 right-6 z-50"
>
    {{-- Floating Action Button --}}
    <button
        wire:click="toggle"
        class="w-14 h-14 rounded-full bg-copper text-white shadow-lg hover:bg-copper-dark transition-all duration-200 flex items-center justify-center"
        :class="{ 'rotate-45 bg-copper-dark': isOpen }"
        aria-label="Toggle AI Assistant"
    >
        <x-lucide-message-square-more class="w-6 h-6" />
    </button>

    {{-- Chat Panel --}}
    <div
        x-show="isOpen"
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
                <button wire:click="startNewConversation" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors" title="New conversation">
                    <x-lucide-plus class="w-4 h-4" />
                </button>
                <button wire:click="toggle" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors" title="Close">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- Conversation sidebar toggle + Messages area --}}
        <div class="flex flex-1 overflow-hidden">
            {{-- Conversation list (collapsible) --}}
            <div
                x-show="showConversations"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                class="w-48 border-r border-slate-200 bg-slate-50/50 overflow-y-auto shrink-0"
            >
                <div class="p-2 space-y-0.5">
                    @foreach($conversations as $conv)
                        <button
                            wire:click="selectConversation({{ $conv['id'] }})"
                            class="w-full text-left px-2.5 py-2 rounded-lg text-xs transition-colors {{ $conv['id'] === $activeConversationId ? 'bg-copper/10 text-copper font-medium' : 'text-slate-600 hover:bg-slate-100' }}"
                        >
                            <span class="block truncate">{{ $conv['title'] ?? 'New conversation' }}</span>
                            <span class="block text-[10px] text-slate-400 mt-0.5">{{ $conv['messages_count'] }} messages</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Messages --}}
            <div
                x-ref="messagesContainer"
                class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50"
            >
                @if($messages->isEmpty() && !$isStreaming)
                    <div class="flex flex-col items-center justify-center h-full text-center text-slate-400 px-4">
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
                    @endif
                @endforeach

                {{-- Live streaming message --}}
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

                {{-- Tool call in-progress indicator --}}
                <div x-show="currentToolName" class="chat chat-start">
                    <div class="chat-bubble bg-amber-50 border border-amber-200 text-amber-700 text-xs flex items-center gap-2">
                        <span class="loading loading-spinner loading-xs"></span>
                        <span x-text="'Running: ' + currentToolName"></span>
                    </div>
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
                    @keydown.window.slash.prevent="isOpen && $refs.messageInput?.focus()"
                />
                <button
                    type="submit"
                    class="btn btn-sm btn-copper transition-all min-w-[36px]"
                    :disabled="isStreaming || !$wire.newMessage.trim()"
                    wire:loading.attr="disabled"
                >
                    <x-lucide-send class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>
</div>