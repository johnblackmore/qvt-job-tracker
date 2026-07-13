<?php

namespace App\Services\Ai\Assistants;

use App\Mcp\Servers\QvtServer;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiModelConfig;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
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
use Throwable;

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

        $fallback = config('ai.assistants.chat-agent');
        $settings = app(AiAssistantConfigSettings::class);
        $configRecord = $settings->chat_agent_config_id
            ? AiModelConfig::find($settings->chat_agent_config_id)
            : null;

        $provider = $conversation->provider ?: ($configRecord?->provider ?? $fallback['provider']);
        $model = $conversation->model ?: ($configRecord?->model ?? $fallback['model']);

        $prism = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt(view($fallback['system_prompt'])->render())
            ->withMessages($this->buildMessages($conversation))
            ->withTools($this->resolveTools())
            ->withMaxSteps($fallback['max_steps'])
            ->usingTemperature($fallback['temperature'])
            ->withMaxTokens($fallback['max_tokens']);

        $onComplete = $this->onComplete($conversation);

        return response()->stream(function () use ($prism, $onComplete): void {
            $collectedEvents = new Collection;
            $hasError = false;

            try {
                $events = $prism->asStream();

                foreach ($events as $event) {
                    $collectedEvents->push($event);

                    if (connection_aborted() !== 0) {
                        break;
                    }

                    echo vsprintf("event: %s\ndata: %s\n\n", [
                        $event->type()->value,
                        json_encode($event->toArray()),
                    ]);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (PrismRateLimitedException $e) {
                $hasError = true;
                Log::warning('AI chat rate limited', [
                    'conversation_id' => $prism->conversation?->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                $this->sendErrorEvent('rate_limit', 'Rate limit reached. Please wait a moment and try again.');
            } catch (PrismProviderOverloadedException $e) {
                $hasError = true;
                Log::warning('AI chat provider overloaded', [
                    'message' => $e->getMessage(),
                ]);

                $this->sendErrorEvent('provider_overloaded', 'The AI service is temporarily unavailable. Please try again later.');
            } catch (PrismRequestTooLargeException $e) {
                $hasError = true;
                Log::warning('AI chat request too large', [
                    'message' => $e->getMessage(),
                ]);

                $this->sendErrorEvent('request_too_large', 'Your message is too long. Please shorten it and try again.');
            } catch (PrismException $e) {
                $hasError = true;
                Log::error('AI chat provider error', [
                    'message' => $e->getMessage(),
                ]);

                $this->sendErrorEvent('provider_error', 'Something went wrong with the AI service. Please try again.');
            } catch (Throwable $e) {
                $hasError = true;
                Log::error('AI chat unexpected error', [
                    'message' => $e->getMessage(),
                ]);

                $this->sendErrorEvent('unexpected_error', 'An unexpected error occurred. Please try again.');
            }

            if (! $hasError && $onComplete !== null) {
                $onComplete($prism, $collectedEvents);
            }

            echo "event: stream_end\ndata: {\"done\": true}\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    protected function sendErrorEvent(string $type, string $message): void
    {
        echo vsprintf("event: error\ndata: %s\n\n", [
            json_encode([
                'error_type' => $type,
                'message' => $message,
                'recoverable' => true,
            ]),
        ]);

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
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
