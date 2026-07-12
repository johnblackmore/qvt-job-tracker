<?php

namespace App\Services\Ai\Assistants;

use App\Mcp\Servers\QvtServer;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
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

    /** @return array<int, UserMessage|AssistantMessage> */
    private function buildMessages(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function (AiMessage $message): UserMessage|AssistantMessage {
                return match ($message->role) {
                    'user' => new UserMessage($message->content ?? ''),
                    'assistant' => new AssistantMessage($message->content ?? ''),
                    default => new UserMessage($message->content ?? ''),
                };
            })
            ->all();
    }

    /** @return array<int, Tool> */
    private function resolveTools(): array
    {
        return array_map(
            fn (string $toolClass): Tool => (new Tool)->make(app($toolClass)),
            QvtServer::toolClasses()
        );
    }

    /** @return callable(PendingRequest, Collection<int, StreamEvent>): void */
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

            $toolCallsArray = array_map(fn ($tc) => $tc->toArray(), $toolCalls);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $toolCallsArray,
                'input_tokens' => $usage?->promptTokens,
                'output_tokens' => $usage?->completionTokens,
                'cost_tokens' => ($usage?->promptTokens ?? 0) + ($usage?->completionTokens ?? 0),
            ]);

            if (! $conversation->title && $conversation->messages()->count() <= 2) {
                $conversation->update([
                    'title' => mb_substr($content, 0, 80),
                ]);
            }

            Cache::put('chat:updated:'.$conversation->id, now()->timestamp, 10);
        };
    }
}
