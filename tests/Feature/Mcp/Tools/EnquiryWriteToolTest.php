<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\CreateEnquiryTool;
use App\Mcp\Tools\LinkEnquiryToCustomerTool;
use App\Mcp\Tools\RespondToEnquiryTool;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryWriteToolTest extends TestCase
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

    public function test_create_enquiry_preview_no_db_write(): void
    {
        $countBefore = Enquiry::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryTool::class, [
                'source' => 'phone',
                'message' => 'Test enquiry message',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->etc();
        });

        $this->assertEquals($countBefore, Enquiry::count());
    }

    public function test_create_enquiry_confirmed_creates_record(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryTool::class, [
                'source' => 'phone',
                'subject' => 'Solar panel question',
                'message' => 'Interested in solar for my van',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->has('enquiry.id');
            $json->where('enquiry.source', 'phone');
            $json->where('enquiry.subject', 'Solar panel question');
            $json->where('enquiry.message', 'Interested in solar for my van');
            $json->where('enquiry.status', 'new');
            $json->etc();
        });

        $this->assertDatabaseHas('enquiries', [
            'source' => 'phone',
            'subject' => 'Solar panel question',
            'message' => 'Interested in solar for my van',
            'status' => 'new',
        ]);
    }

    public function test_create_enquiry_sets_staff_user_id_from_auth_user(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryTool::class, [
                'message' => 'Test message',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('enquiries', [
            'message' => 'Test message',
            'staff_user_id' => $this->admin->id,
        ]);
    }

    public function test_create_enquiry_allows_unlinked_customer(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryTool::class, [
                'message' => 'Walk-in enquiry',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('enquiry.customer_id', null);
            $json->where('enquiry.customer_name', null);
            $json->etc();
        });
    }

    public function test_create_enquiry_rejects_missing_message(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryTool::class, [
                'subject' => 'Missing message',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_link_enquiry_to_customer_preview_no_db_write(): void
    {
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create(['customer_id' => null]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(LinkEnquiryToCustomerTool::class, [
                'enquiry_id' => $enquiry->id,
                'customer_id' => $customer->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();

        $enquiry->refresh();
        $this->assertNull($enquiry->customer_id);
    }

    public function test_link_enquiry_to_customer_confirmed_links(): void
    {
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(LinkEnquiryToCustomerTool::class, [
                'enquiry_id' => $enquiry->id,
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('enquiry.customer_id', $customer->id);
            $json->where('enquiry.customer_name', $customer->name);
            $json->etc();
        });

        $this->assertDatabaseHas('enquiries', [
            'id' => $enquiry->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_link_enquiry_to_customer_allows_overwrite(): void
    {
        $oldCustomer = Customer::factory()->create();
        $newCustomer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create(['customer_id' => $oldCustomer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(LinkEnquiryToCustomerTool::class, [
                'enquiry_id' => $enquiry->id,
                'customer_id' => $newCustomer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $enquiry->refresh();
        $this->assertEquals($newCustomer->id, $enquiry->customer_id);
    }

    public function test_link_enquiry_rejects_invalid_enquiry_id(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(LinkEnquiryToCustomerTool::class, [
                'enquiry_id' => 99999,
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_respond_to_enquiry_preview_no_db_write(): void
    {
        $enquiry = Enquiry::factory()->create(['status' => 'new']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(RespondToEnquiryTool::class, [
                'id' => $enquiry->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();

        $enquiry->refresh();
        $this->assertEquals('new', $enquiry->status);
        $this->assertNull($enquiry->responded_at);
    }

    public function test_respond_to_enquiry_confirmed_sets_status_and_timestamp(): void
    {
        $enquiry = Enquiry::factory()->create([
            'status' => 'new',
            'responded_at' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(RespondToEnquiryTool::class, [
                'id' => $enquiry->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('enquiry.status', 'responded');
            $json->has('enquiry.responded_at');
            $json->etc();
        });

        $enquiry->refresh();
        $this->assertEquals('responded', $enquiry->status);
        $this->assertNotNull($enquiry->responded_at);
    }

    public function test_respond_to_enquiry_is_idempotent(): void
    {
        $enquiry = Enquiry::factory()->create([
            'status' => 'responded',
            'responded_at' => now(),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(RespondToEnquiryTool::class, [
                'id' => $enquiry->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->etc();
        });
    }

    public function test_write_enquiry_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(CreateEnquiryTool::class, [
                'message' => 'Should fail',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
