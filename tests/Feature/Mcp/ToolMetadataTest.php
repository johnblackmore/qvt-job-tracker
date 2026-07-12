<?php

namespace Tests\Feature\Mcp;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ToolMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    public function test_every_tool_has_a_description(): void
    {
        $tools = $this->listTools();

        foreach ($tools as $tool) {
            $this->assertNotEmpty(
                $tool['description'] ?? null,
                "Tool [{$tool['name']}] is missing a description."
            );
        }
    }

    public function test_every_tool_has_an_input_schema(): void
    {
        $tools = $this->listTools();

        foreach ($tools as $tool) {
            $this->assertNotEmpty(
                $tool['inputSchema'] ?? null,
                "Tool [{$tool['name']}] is missing an inputSchema."
            );
        }
    }

    public function test_every_write_tool_has_preview_and_confirmed_params(): void
    {
        $tools = $this->listTools();

        $writeToolNames = [
            'create-customer-tool',
            'update-customer-tool',
            'delete-customer-tool',
            'create-quote-tool',
            'create-quote-from-template-tool',
            'add-quote-line-item-tool',
            'update-quote-status-tool',
            'create-order-tool',
            'update-order-status-tool',
            'update-deposit-tool',
            'schedule-installation-tool',
            'create-enquiry-tool',
            'link-enquiry-to-customer-tool',
            'respond-to-enquiry-tool',
            'send-quote-email-tool',
        ];

        foreach ($tools as $tool) {
            if (! in_array($tool['name'], $writeToolNames, true)) {
                continue;
            }

            $schema = $tool['inputSchema'] ?? [];
            $props = $schema['properties'] ?? [];

            $this->assertArrayHasKey(
                'preview',
                $props,
                "Write tool [{$tool['name']}] must expose a 'preview' parameter."
            );
            $this->assertArrayHasKey(
                'confirmed',
                $props,
                "Write tool [{$tool['name']}] must expose a 'confirmed' parameter."
            );
        }
    }

    public function test_calling_tool_without_required_args_returns_clear_error(): void
    {
        $response = $this->callTool('get-customer-tool', []);

        $this->assertTrue(
            (bool) ($response['result']['isError'] ?? false),
            'Expected the tool to return an error (isError=true) when required args are missing.'
        );

        $text = $response['result']['content'][0]['text'] ?? '';
        $this->assertNotEmpty($text, 'Expected a non-empty error message in result.content[0].text.');
    }

    public function test_read_tools_return_message_field(): void
    {
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $enquiry = Enquiry::factory()->create();

        $cases = [
            ['list-customers-tool', []],
            ['get-customer-tool', ['id' => $customer->id]],
            ['list-products-tool', []],
            ['get-product-tool', ['id' => $product->id]],
            ['list-orders-tool', []],
            ['get-order-tool', ['id' => $order->id]],
            ['list-enquiries-tool', []],
            ['get-dashboard-stats-tool', []],
            ['get-quote-activity-tool', []],
            ['get-weekly-summary-tool', []],
        ];

        foreach ($cases as [$name, $args]) {
            $payload = $this->callTool($name, $args);
            $structured = $payload['result']['structuredContent'] ?? [];

            $this->assertNotEmpty(
                $structured['message'] ?? null,
                "Read tool [{$name}] did not return a 'message' field."
            );
        }
    }

    public function test_get_tools_return_url_field(): void
    {
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $product->suppliers()->attach($supplier->id, ['is_preferred' => true, 'trade_price' => 50]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $enquiry = Enquiry::factory()->create(['customer_id' => $customer->id]);

        $cases = [
            ['get-customer-tool', ['id' => $customer->id]],
            ['get-product-tool', ['id' => $product->id]],
            ['list-orders-tool', []],
            ['get-order-tool', ['id' => $order->id]],
        ];

        foreach ($cases as [$name, $args]) {
            $payload = $this->callTool($name, $args);
            $structured = $payload['result']['structuredContent'] ?? [];

            $url = $structured['url']
                ?? ($structured['data'][0]['url'] ?? null);

            $this->assertNotEmpty(
                $url,
                "Tool [{$name}] did not return a 'url' field (or 'data[].url' for list tools)."
            );

            $this->assertStringContainsString(
                (string) config('app.url'),
                $url,
                "Tool [{$name}] returned a URL that does not include the app base URL."
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listTools(): array
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ]);

        $response->assertOk();

        return $response->json('result.tools') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function callTool(string $name, array $arguments): array
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => $name,
                    'arguments' => $arguments,
                ],
            ]);

        $response->assertOk();

        return $response->json();
    }
}
