<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\GetDashboardStatsTool;
use App\Mcp\Tools\GetQuoteActivityTool;
use App\Mcp\Tools\GetWeeklySummaryTool;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardToolsTest extends TestCase
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

    public function test_get_dashboard_stats_returns_all_categories(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create(['customer_id' => $customer->id, 'status' => 'draft']);
        Quote::factory()->create(['customer_id' => $customer->id, 'status' => 'accepted']);
        Order::factory()->create(['customer_id' => $customer->id, 'status' => 'pending', 'deposit_paid' => 100]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetDashboardStatsTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('message');
            $json->has('stats.customers');
            $json->has('stats.quotes');
            $json->has('stats.orders');
            $json->has('stats.enquiries');
            $json->has('stats.revenue_pipeline');
            $json->where('stats.quotes.by_status.draft', 1);
            $json->where('stats.quotes.by_status.accepted', 1);
            $json->where('stats.orders.deposit_collected', 100.0);
            $json->etc();
        });
    }

    public function test_get_dashboard_stats_message_includes_date(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(GetDashboardStatsTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $expectedDate = now()->format('d F Y');
            $json->where('message', fn (string $msg) => str_contains($msg, $expectedDate));
            $json->etc();
        });
    }

    public function test_get_quote_activity_filters_by_date_range(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subDays(10),
        ]);
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetQuoteActivityTool::class, [
                'since' => now()->subDays(5)->format('Y-m-d'),
                'until' => now()->format('Y-m-d'),
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('activity.quotes_created.count', 1);
            $json->etc();
        });
    }

    public function test_get_quote_activity_returns_four_activity_types(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create(['customer_id' => $customer->id, 'status' => 'draft']);
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetQuoteActivityTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('activity.quotes_created');
            $json->has('activity.quotes_sent');
            $json->has('activity.quotes_accepted');
            $json->has('activity.quotes_declined');
            $json->etc();
        });
    }

    public function test_get_quote_activity_no_params_defaults_to_last_seven_days(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->count(3)->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetQuoteActivityTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('activity.quotes_created.count', 3);
            $json->etc();
        });
    }

    public function test_get_quote_activity_includes_top_recent_quotes_with_url(): void
    {
        $customer = Customer::factory()->create(['name' => 'John Blackmore']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-RECENT-001',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetQuoteActivityTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($quote) {
            $json->has('recent_quotes', 1);
            $json->where('recent_quotes.0.reference_number', 'Q-RECENT-001');
            $json->where('recent_quotes.0.customer_name', 'John Blackmore');
            $json->where('recent_quotes.0.url', fn (string $url) => str_contains($url, "/quotes/{$quote->id}"));
            $json->etc();
        });
    }

    public function test_get_weekly_summary_returns_narrative_message(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'grand_total' => 1500.00,
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'deposit_paid' => 450.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetWeeklySummaryTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->where('summary.new_customers', 1);
            $json->where('summary.accepted_quotes_count', 1);
            $json->where('summary.accepted_quotes_value', 1500.00);
            $json->where('summary.deposit_collected', 450.00);
            $json->has('message');
            $json->etc();
        });
    }

    public function test_get_weekly_summary_includes_pending_follow_ups(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'sent_at' => now()->subDays(5),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetWeeklySummaryTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('pending_follow_ups', 1);
            $json->where('pending_follow_ups.0.days_waiting', 5);
            $json->etc();
        });
    }

    public function test_get_weekly_summary_includes_top_customers_highlights(): void
    {
        $customer1 = Customer::factory()->create(['name' => 'Top Customer']);
        $customer2 = Customer::factory()->create(['name' => 'Second Customer']);

        Quote::factory()->create([
            'customer_id' => $customer1->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'grand_total' => 5000.00,
        ]);
        Quote::factory()->create([
            'customer_id' => $customer2->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'grand_total' => 2000.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetWeeklySummaryTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('highlights', 2);
            $json->where('highlights.0.customer_name', 'Top Customer');
            $json->where('highlights.0.total_value', 5000.00);
            $json->etc();
        });
    }

    public function test_dashboard_tools_are_read_only(): void
    {
        $countBefore = Quote::count();

        QvtServer::actingAs($this->admin)
            ->tool(GetDashboardStatsTool::class, []);

        QvtServer::actingAs($this->admin)
            ->tool(GetQuoteActivityTool::class, []);

        QvtServer::actingAs($this->admin)
            ->tool(GetWeeklySummaryTool::class, []);

        $this->assertEquals($countBefore, Quote::count());
    }

    public function test_dashboard_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(GetDashboardStatsTool::class, []);

        $response->assertHasErrors();
    }

    public function test_dashboard_tools_reject_non_admin_quotes_and_orders(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(GetWeeklySummaryTool::class, []);

        $response->assertHasErrors();
    }
}
