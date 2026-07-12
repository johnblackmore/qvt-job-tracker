<?php

namespace Tests\Feature\Http\Controllers\Ai;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->conversation = $this->admin->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);
    }

    public function test_stream_returns_sse_response(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hello!'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ai.chat.stream', [
                'conversation' => $this->conversation->id,
                'message' => 'Hi',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    }

    public function test_stream_saves_user_message(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hello!'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.ai.chat.stream', [
                'conversation' => $this->conversation->id,
                'message' => 'Hi there',
            ]));

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hi there',
        ]);
    }

    public function test_stream_requires_authentication(): void
    {
        $response = $this->get(route('admin.ai.chat.stream', [
            'conversation' => $this->conversation->id,
            'message' => 'Hi',
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_stream_requires_admin_role(): void
    {
        $role = Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer = User::factory()->create();
        $installer->assignRole($role);

        $response = $this->actingAs($installer)
            ->get(route('admin.ai.chat.stream', [
                'conversation' => $this->conversation->id,
                'message' => 'Hi',
            ]));

        $response->assertForbidden();
    }

    public function test_stream_guards_conversation_ownership(): void
    {
        $otherUser = User::factory()->create();
        $otherConversation = $otherUser->aiConversations()->create([
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ai.chat.stream', [
                'conversation' => $otherConversation->id,
                'message' => 'Hi',
            ]));

        $response->assertForbidden();
    }

    public function test_stream_validates_message_length(): void
    {
        Prism::fake([]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ai.chat.stream', [
                'conversation' => $this->conversation->id,
                'message' => str_repeat('a', 4001),
            ]));

        $response->assertSessionHasErrors('message');
    }
}
