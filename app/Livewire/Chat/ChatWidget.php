<?php

namespace App\Livewire\Chat;

use App\Models\AiConversation;
use App\Models\AiModelConfig;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attribute\On;
use Livewire\Component;

class ChatWidget extends Component
{
    public bool $isOpen = false;

    public ?int $activeConversationId = null;

    public array $conversations = [];

    public string $newMessage = '';

    public bool $isStreaming = false;

    public function mount(): void
    {
        $this->loadConversations();
    }

    #[On('toggle-chat')]
    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen && ! $this->activeConversationId) {
            $this->startNewConversation();
        }

        if ($this->isOpen) {
            $this->dispatch('chat-opened');
        }
    }

    public function startNewConversation(): void
    {
        $settings = app(AiAssistantConfigSettings::class);
        $configRecord = $settings->chat_agent_config_id
            ? AiModelConfig::find($settings->chat_agent_config_id)
            : null;

        $conversation = auth()->user()->aiConversations()->create([
            'provider' => $configRecord?->provider ?? config('ai.assistants.chat-agent.provider'),
            'model' => $configRecord?->model ?? config('ai.assistants.chat-agent.model'),
        ]);

        $this->activeConversationId = $conversation->id;
        $this->loadConversations();
    }

    public function selectConversation(int $id): void
    {
        $conversation = AiConversation::where('user_id', auth()->id())->findOrFail($id);
        $this->activeConversationId = $conversation->id;
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => 'required|string|max:4000']);

        if (! $this->activeConversationId) {
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

    public function retryLastMessage(): void
    {
        if (! $this->activeConversationId) {
            return;
        }

        $this->isStreaming = true;

        $this->dispatch('start-streaming', conversationId: $this->activeConversationId);
    }

    #[On('stream-completed')]
    public function refreshFromStream(): void
    {
        $this->loadConversations();
        $this->isStreaming = false;
    }

    public function loadConversations(): void
    {
        $this->conversations = auth()->user()
            ->aiConversations()
            ->withCount('messages')
            ->latest('updated_at')
            ->get()
            ->toArray();
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
