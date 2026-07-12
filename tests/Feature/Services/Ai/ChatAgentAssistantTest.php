<?php

namespace Tests\Feature\Services\Ai;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\Assistants\ChatAgentAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ChatAgentAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->conversation = $this->user->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);
    }

    public function test_stream_response_returns_streamed_response(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hello! How can I help you today?'),
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $response = $assistant->streamResponse($this->conversation, $this->user, 'Hi there');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_stream_response_saves_user_message(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hello! How can I help you today?'),
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $assistant->streamResponse($this->conversation, $this->user, 'Hi there');

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hi there',
        ]);
    }

    public function test_stream_response_callback_saves_assistant_message(): void
    {
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('I can help you with customers, quotes, and orders.')
                ->withUsage(new Usage(25, 12)),
        ]);

        $assistant = app(ChatAgentAssistant::class);

        // Stream the response (the callback fires when the response is consumed)
        $response = $assistant->streamResponse($this->conversation, $this->user, 'What can you do?');

        // Consume the streamed response to trigger the callback
        ob_start();
        $response->send();
        ob_end_clean();

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'I can help you with customers, quotes, and orders.',
        ]);

        $fake->assertCallCount(1);
    }

    public function test_stream_response_logs_token_usage(): void
    {
        Prism::fake([
            TextResponseFake::make()
                ->withText('Sure, let me look that up.')
                ->withUsage(new Usage(50, 30)),
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $response = $assistant->streamResponse($this->conversation, $this->user, 'Show me the dashboard stats');

        ob_start();
        $response->send();
        ob_end_clean();

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'input_tokens' => 50,
            'output_tokens' => 30,
            'cost_tokens' => 80,
        ]);
    }

    public function test_stream_response_sets_conversation_title_from_first_exchange(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('I can help you manage your campervan electrical installation business.'),
        ]);

        $untitled = $this->user->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
            'title' => null,
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $response = $assistant->streamResponse($untitled, $this->user, 'What do you do?');

        ob_start();
        $response->send();
        ob_end_clean();

        $this->assertNotNull($untitled->fresh()->title);
        $this->assertStringContainsString('campervan electrical installation business', $untitled->fresh()->title);
    }

    public function test_stream_response_does_not_overwrite_existing_title(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Here are your stats.'),
        ]);

        $titled = $this->user->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
            'title' => 'Existing title',
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $response = $assistant->streamResponse($titled, $this->user, 'Show stats');

        ob_start();
        $response->send();
        ob_end_clean();

        $this->assertEquals('Existing title', $titled->fresh()->title);
    }

    public function test_stream_response_can_be_called_without_new_message(): void
    {
        $this->conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hello',
        ]);

        Prism::fake([
            TextResponseFake::make()->withText('Hi again!'),
        ]);

        $assistant = app(ChatAgentAssistant::class);
        $response = $assistant->streamResponse($this->conversation, $this->user);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
