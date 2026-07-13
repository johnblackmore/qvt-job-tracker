<?php

namespace Tests\Feature\Livewire\AiModelConfigs;

use App\Livewire\AiModelConfigs\AiAssistantConfigSettings;
use App\Livewire\AiModelConfigs\AiModelConfigForm;
use App\Livewire\AiModelConfigs\AiModelConfigList;
use App\Models\AiModelConfig;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings as Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiModelConfigTest extends TestCase
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

    public function test_list_renders_empty_state(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiModelConfigList::class)
            ->assertSee('No model configs yet');
    }

    public function test_list_shows_configs(): void
    {
        $config = AiModelConfig::factory()->create([
            'label' => 'Test Config',
            'provider' => 'opencode',
            'model' => 'test-model',
        ]);

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigList::class)
            ->assertSee('Test Config')
            ->assertSee('opencode')
            ->assertSee('test-model');
    }

    public function test_create_form_validation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiModelConfigForm::class)
            ->call('save')
            ->assertHasErrors(['label', 'provider', 'model']);
    }

    public function test_create_form_creates_config(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiModelConfigForm::class)
            ->set('label', 'OpenCode DeepSeek')
            ->set('provider', 'opencode')
            ->set('model', 'deepseek-v4-flash-free')
            ->call('save');

        $this->assertDatabaseHas('ai_model_configs', [
            'label' => 'OpenCode DeepSeek',
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
        ]);
    }

    public function test_create_form_validates_provider(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiModelConfigForm::class)
            ->set('label', 'Invalid Provider')
            ->set('provider', 'nonexistent-provider')
            ->set('model', 'some-model')
            ->call('save')
            ->assertHasErrors(['provider']);
    }

    public function test_edit_form_populates_existing_config(): void
    {
        $config = AiModelConfig::factory()->create([
            'label' => 'Original Label',
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
        ]);

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigForm::class, ['aiModelConfig' => $config->id])
            ->assertSet('label', 'Original Label')
            ->assertSet('provider', 'anthropic')
            ->assertSet('model', 'claude-3-5-sonnet');
    }

    public function test_edit_form_updates_config(): void
    {
        $config = AiModelConfig::factory()->create([
            'label' => 'Old Label',
            'provider' => 'opencode',
            'model' => 'old-model',
        ]);

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigForm::class, ['aiModelConfig' => $config->id])
            ->set('label', 'Updated Label')
            ->set('model', 'new-model')
            ->call('save');

        $this->assertDatabaseHas('ai_model_configs', [
            'id' => $config->id,
            'label' => 'Updated Label',
            'model' => 'new-model',
        ]);
    }

    public function test_delete_config(): void
    {
        $config = AiModelConfig::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigList::class)
            ->call('delete', $config->id);

        $this->assertDatabaseMissing('ai_model_configs', ['id' => $config->id]);
    }

    public function test_delete_config_clears_assignment(): void
    {
        $config = AiModelConfig::factory()->create();

        $settings = app(Settings::class);
        $settings->chat_agent_config_id = $config->id;
        $settings->save();

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigList::class)
            ->call('delete', $config->id);

        $settings->refresh();
        $this->assertNull($settings->chat_agent_config_id);
    }

    public function test_assign_config_to_assistant(): void
    {
        $config = AiModelConfig::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(AiModelConfigList::class)
            ->call('assignToAssistant', $config->id, 'chat-agent');

        $settings = app(Settings::class);
        $this->assertEquals($config->id, $settings->chat_agent_config_id);
    }

    public function test_assistant_settings_saves(): void
    {
        $config1 = AiModelConfig::factory()->create();
        $config2 = AiModelConfig::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(AiAssistantConfigSettings::class)
            ->set('chat_agent_config_id', $config1->id)
            ->set('product_url_extractor_config_id', $config2->id)
            ->call('save');

        $settings = app(Settings::class);
        $this->assertEquals($config1->id, $settings->chat_agent_config_id);
        $this->assertEquals($config2->id, $settings->product_url_extractor_config_id);
    }

    public function test_assistant_settings_can_unset(): void
    {
        $config = AiModelConfig::factory()->create();

        $settings = app(Settings::class);
        $settings->chat_agent_config_id = $config->id;
        $settings->save();

        Livewire::actingAs($this->admin)
            ->test(AiAssistantConfigSettings::class)
            ->set('chat_agent_config_id', '')
            ->call('save');

        $settings->refresh();
        $this->assertNull($settings->chat_agent_config_id);
    }

    public function test_installer_cannot_access_config_pages(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $this->actingAs($installer)
            ->get(route('admin.ai.configs.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_config_pages(): void
    {
        $this->get(route('admin.ai.configs.index'))
            ->assertRedirect(route('login'));
    }
}
