<?php

namespace App\Livewire\AiAssistants;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiModelConfig;
use Livewire\Component;
use Livewire\WithPagination;

class ChatAgentDetail extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public ?int $viewingConversationId = null;

    protected $queryString = [
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'search' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function viewConversation(int $id): void
    {
        $this->viewingConversationId = $id;
    }

    public function closeConversation(): void
    {
        $this->viewingConversationId = null;
    }

    public function render()
    {
        $conversationsQuery = AiConversation::with('user', 'messages');

        if ($this->search) {
            $conversationsQuery->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->dateFrom) {
            $conversationsQuery->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $conversationsQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        $conversations = $conversationsQuery
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        $totalConversations = AiConversation::count();
        $totalMessages = AiMessage::count();
        $totalTokens = AiMessage::sum('input_tokens') + AiMessage::sum('output_tokens');
        $uniqueUsers = AiConversation::distinct('user_id')->count('user_id');

        $providerModelStats = AiConversation::selectRaw('
                ai_conversations.provider,
                ai_conversations.model,
                count(distinct ai_conversations.id) as conversations,
                count(ai_messages.id) as messages,
                coalesce(sum(ai_messages.input_tokens), 0) as input_tokens,
                coalesce(sum(ai_messages.output_tokens), 0) as output_tokens
            ')
            ->leftJoin('ai_messages', 'ai_messages.conversation_id', '=', 'ai_conversations.id')
            ->whereNotNull('ai_conversations.provider')
            ->groupBy('ai_conversations.provider', 'ai_conversations.model')
            ->get()
            ->map(function ($stat) {
                $totalTokens = $stat->input_tokens + $stat->output_tokens;

                return [
                    'provider' => $stat->provider,
                    'model' => $stat->model,
                    'conversations' => (int) $stat->conversations,
                    'messages' => (int) $stat->messages,
                    'input_tokens' => (int) $stat->input_tokens,
                    'output_tokens' => (int) $stat->output_tokens,
                    'total_tokens' => $totalTokens,
                    'avg_tokens' => $stat->conversations > 0 ? round($totalTokens / $stat->conversations) : 0,
                    'cost' => $this->estimateCost((int) $stat->input_tokens, (int) $stat->output_tokens, $stat->provider, $stat->model),
                ];
            })
            ->sortByDesc('total_tokens');

        $userStats = AiConversation::selectRaw('
                ai_conversations.user_id,
                count(distinct ai_conversations.id) as conversations,
                count(ai_messages.id) as messages,
                coalesce(sum(ai_messages.input_tokens), 0) + coalesce(sum(ai_messages.output_tokens), 0) as total_tokens
            ')
            ->leftJoin('ai_messages', 'ai_messages.conversation_id', '=', 'ai_conversations.id')
            ->with('user')
            ->groupBy('ai_conversations.user_id')
            ->get()
            ->map(function ($stat) {
                return [
                    'user' => $stat->user,
                    'conversations' => (int) $stat->conversations,
                    'messages' => (int) $stat->messages,
                    'total_tokens' => (int) $stat->total_tokens,
                    'cost' => $this->estimateCostForUser($stat->user_id),
                ];
            })
            ->sortByDesc('total_tokens');

        $viewingConversation = null;
        if ($this->viewingConversationId) {
            $viewingConversation = AiConversation::with('messages', 'user')
                ->find($this->viewingConversationId);
        }

        return view('livewire.ai-assistants.chat-agent-detail', compact(
            'conversations',
            'totalConversations', 'totalMessages', 'totalTokens', 'uniqueUsers',
            'providerModelStats', 'userStats',
            'viewingConversation',
        ));
    }

    private function estimateCost(int $inputTokens, int $outputTokens, string $provider, string $model): ?float
    {
        $config = AiModelConfig::where('provider', $provider)->where('model', $model)->first();
        if (! $config || $config->input_price === null || $config->output_price === null) {
            return null;
        }

        return ($inputTokens / 1_000_000 * $config->input_price)
             + ($outputTokens / 1_000_000 * $config->output_price);
    }

    private function estimateCostForUser(int $userId): ?float
    {
        $pairs = AiConversation::selectRaw('
                ai_conversations.provider,
                ai_conversations.model,
                coalesce(sum(ai_messages.input_tokens), 0) as input_tokens,
                coalesce(sum(ai_messages.output_tokens), 0) as output_tokens
            ')
            ->leftJoin('ai_messages', 'ai_messages.conversation_id', '=', 'ai_conversations.id')
            ->where('ai_conversations.user_id', $userId)
            ->whereNotNull('ai_conversations.provider')
            ->groupBy('ai_conversations.provider', 'ai_conversations.model')
            ->get();

        $total = 0;
        foreach ($pairs as $pair) {
            $config = AiModelConfig::where('provider', $pair->provider)->where('model', $pair->model)->first();
            if (! $config || $config->input_price === null || $config->output_price === null) {
                continue;
            }

            $total += ($pair->input_tokens / 1_000_000 * $config->input_price)
                   + ($pair->output_tokens / 1_000_000 * $config->output_price);
        }

        return $total > 0 ? $total : null;
    }
}
