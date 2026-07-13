<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\AiConfig\CreateAiModelConfigTool;
use App\Mcp\Tools\AiConfig\DeleteAiModelConfigTool;
use App\Mcp\Tools\AiConfig\GetAiAssistantConfigSettingsTool;
use App\Mcp\Tools\AiConfig\GetAiModelConfigTool;
use App\Mcp\Tools\AiConfig\ListAiModelConfigsTool;
use App\Mcp\Tools\AiConfig\UpdateAiAssistantConfigSettingsTool;
use App\Mcp\Tools\AiConfig\UpdateAiModelConfigTool;
use App\Models\AiModelConfig;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings as Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiConfigToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'installer']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_list_tool_returns_configs(): void
    {
        AiModelConfig::factory()->create(['label' => 'Config A']);
        AiModelConfig::factory()->create(['label' => 'Config B']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListAiModelConfigsTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->where('message', 'Found 2 AI model configs.')
                ->has('data', 2)
                ->etc(),
        ]);
    }

    public function test_get_tool_returns_single_config(): void
    {
        $config = AiModelConfig::factory()->create([
            'label' => 'Test Config',
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetAiModelConfigTool::class, ['id' => $config->id]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->has('config')
                ->where('config.label', 'Test Config')
                ->where('config.provider', 'anthropic')
                ->where('config.model', 'claude-3-5-sonnet')
                ->etc(),
        ]);
    }

    public function test_create_tool_preview_does_not_save(): void
    {
        $countBefore = AiModelConfig::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateAiModelConfigTool::class, [
                'label' => 'Preview Config',
                'provider' => 'opencode',
                'model' => 'test-model',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'preview')
                ->etc(),
        ]);
        $this->assertEquals($countBefore, AiModelConfig::count());
    }

    public function test_create_tool_confirmed_saves(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateAiModelConfigTool::class, [
                'label' => 'New Config',
                'provider' => 'opencode',
                'model' => 'deepseek-v4-flash-free',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->has('url')
                ->has('config.id')
                ->etc(),
        ]);
        $this->assertDatabaseHas('ai_model_configs', [
            'label' => 'New Config',
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);
    }

    public function test_create_tool_validates_provider(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateAiModelConfigTool::class, [
                'label' => 'Bad',
                'provider' => 'not-a-provider',
                'model' => 'foo',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_update_tool_preview_does_not_save(): void
    {
        $config = AiModelConfig::factory()->create(['label' => 'Original']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateAiModelConfigTool::class, [
                'id' => $config->id,
                'label' => 'Updated',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'preview')
                ->etc(),
        ]);
        $this->assertDatabaseHas('ai_model_configs', ['label' => 'Original']);
    }

    public function test_update_tool_confirmed_saves(): void
    {
        $config = AiModelConfig::factory()->create(['label' => 'Original']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateAiModelConfigTool::class, [
                'id' => $config->id,
                'label' => 'Updated Label',
                'model' => 'new-model',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->etc(),
        ]);
        $this->assertDatabaseHas('ai_model_configs', [
            'id' => $config->id,
            'label' => 'Updated Label',
            'model' => 'new-model',
        ]);
    }

    public function test_delete_tool_preview_does_not_delete(): void
    {
        $config = AiModelConfig::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(DeleteAiModelConfigTool::class, [
                'id' => $config->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'preview')
                ->etc(),
        ]);
        $this->assertDatabaseHas('ai_model_configs', ['id' => $config->id]);
    }

    public function test_delete_tool_confirmed_deletes(): void
    {
        $config = AiModelConfig::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(DeleteAiModelConfigTool::class, [
                'id' => $config->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->etc(),
        ]);
        $this->assertDatabaseMissing('ai_model_configs', ['id' => $config->id]);
    }

    public function test_delete_tool_clears_assignment(): void
    {
        $config = AiModelConfig::factory()->create();

        $settings = app(Settings::class);
        $settings->chat_agent_config_id = $config->id;
        $settings->save();

        QvtServer::actingAs($this->admin)
            ->tool(DeleteAiModelConfigTool::class, [
                'id' => $config->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $settings->refresh();
        $this->assertNull($settings->chat_agent_config_id);
    }

    public function test_get_settings_tool(): void
    {
        $config = AiModelConfig::factory()->create();

        $settings = app(Settings::class);
        $settings->chat_agent_config_id = $config->id;
        $settings->save();

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetAiAssistantConfigSettingsTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'completed')
                ->has('assignments')
                ->has('assignments.chat_agent.id')
                ->where('assignments.chat_agent.label', $config->label)
                ->etc(),
        ]);
    }

    public function test_update_settings_tool_confirmed_saves(): void
    {
        $config = AiModelConfig::factory()->create();

        QvtServer::actingAs($this->admin)
            ->tool(UpdateAiAssistantConfigSettingsTool::class, [
                'chat_agent_config_id' => $config->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $settings = app(Settings::class);
        $this->assertEquals($config->id, $settings->chat_agent_config_id);
    }

    public function test_update_settings_tool_preview_does_not_save(): void
    {
        $config = AiModelConfig::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateAiAssistantConfigSettingsTool::class, [
                'chat_agent_config_id' => $config->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(fn ($json) => [
            $json->where('status', 'preview')
                ->etc(),
        ]);

        $settings = app(Settings::class);
        $this->assertNull($settings->chat_agent_config_id);
    }

    public function test_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(CreateAiModelConfigTool::class, [
                'label' => 'Should Fail',
                'provider' => 'opencode',
                'model' => 'test',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
