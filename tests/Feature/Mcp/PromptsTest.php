<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Prompts\QuoteAssistantPrompt;
use App\Mcp\Prompts\WeeklyReportGeneratorPrompt;
use App\Mcp\Servers\QvtServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromptsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_quote_assistant_prompt_professional_tone(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->prompt(QuoteAssistantPrompt::class, ['tone' => 'professional']);

        $response->assertOk();
        $response->assertSee([
            'professional quote assistant',
            'formal, precise tone',
        ]);
    }

    public function test_quote_assistant_prompt_casual_tone(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->prompt(QuoteAssistantPrompt::class, ['tone' => 'casual']);

        $response->assertOk();
        $response->assertSee([
            'friendly, helpful quote assistant',
            'first names',
        ]);
    }

    public function test_weekly_report_generator_prompt_includes_week_starting(): void
    {
        $weekStarting = now()->startOfWeek()->format('Y-m-d');
        $formattedDate = now()->startOfWeek()->format('d F Y');

        $response = QvtServer::actingAs($this->admin)
            ->prompt(WeeklyReportGeneratorPrompt::class, ['week_starting' => $weekStarting]);

        $response->assertOk();
        $response->assertSee([
            $formattedDate,
            'weekly business report',
            'get-weekly-summary',
        ]);
    }
}
