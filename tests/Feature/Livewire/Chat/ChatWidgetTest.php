<?php

namespace Tests\Feature\Livewire\Chat;

use App\Livewire\Chat\ChatWidget;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        config()->set('ai.assistants.chat-agent.provider', 'opencode');
        config()->set('ai.assistants.chat-agent.model', 'deepseek-v4-flash-free');
    }

    public function test_component_renders(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->assertStatus(200);
    }

    public function test_toggle_opens_chat(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->assertSet('isOpen', false)
            ->call('toggle')
            ->assertSet('isOpen', true);
    }

    public function test_toggle_closes_chat(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->call('toggle')
            ->call('toggle')
            ->assertSet('isOpen', false);
    }

    public function test_toggle_creates_new_conversation_if_none_active(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->call('toggle')
            ->assertSet('isOpen', true)
            ->assertSet('activeConversationId', fn ($id) => is_int($id));
    }

    public function test_start_new_conversation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->call('startNewConversation')
            ->assertSet('activeConversationId', fn ($id) => is_int($id));

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->admin->id,
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);
    }

    public function test_send_message_creates_conversation_if_none_active(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('newMessage', 'Hello!')
            ->call('sendMessage')
            ->assertSet('activeConversationId', fn ($id) => is_int($id));
    }

    public function test_send_message_saves_user_message_to_db(): void
    {
        $conversation = $this->admin->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('activeConversationId', $conversation->id)
            ->set('newMessage', 'Show me my customers')
            ->call('sendMessage')
            ->assertSet('newMessage', '')
            ->assertSet('isStreaming', true);

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Show me my customers',
        ]);
    }

    public function test_send_message_dispatches_start_streaming_event(): void
    {
        $conversation = $this->admin->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('activeConversationId', $conversation->id)
            ->set('newMessage', 'Hello')
            ->call('sendMessage')
            ->assertDispatched('start-streaming', conversationId: $conversation->id);
    }

    public function test_select_conversation_switches_active(): void
    {
        $conversation1 = $this->admin->aiConversations()->create();
        $conversation2 = $this->admin->aiConversations()->create();

        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('activeConversationId', $conversation1->id)
            ->call('selectConversation', $conversation2->id)
            ->assertSet('activeConversationId', $conversation2->id);
    }

    public function test_select_conversation_guards_ownership(): void
    {
        $otherUser = User::factory()->create();
        $otherConversation = $otherUser->aiConversations()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->call('selectConversation', $otherConversation->id);
    }

    public function test_refresh_from_stream_clears_streaming(): void
    {
        $conversation = $this->admin->aiConversations()->create();

        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('activeConversationId', $conversation->id)
            ->set('isStreaming', true)
            ->call('refreshFromStream')
            ->assertSet('isStreaming', false);
    }

    public function test_send_message_validates_required(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertHasErrors('newMessage');
    }

    public function test_send_message_validates_max_length(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ChatWidget::class)
            ->set('newMessage', str_repeat('a', 4001))
            ->call('sendMessage')
            ->assertHasErrors('newMessage');
    }
}
