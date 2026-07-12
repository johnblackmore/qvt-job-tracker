<?php

namespace Tests\Feature\Netlify;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\ProcessedNetlifySubmission;
use App\Models\User;
use App\Services\NetlifyFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NetlifyFormSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_ID = 'test-site-id';

    private function mockSubmission(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_'.fake()->uuid(),
            'number' => 1,
            'name' => 'David Hornby',
            'email' => 'djh83y@me.com',
            'first_name' => 'David',
            'last_name' => 'Hornby',
            'summary' => 'Project details: Currently has no solar...',
            'body' => "Your name: David Hornby\nEmail address: djh83y@me.com\n...",
            'data' => [
                'name' => 'David Hornby',
                'email' => 'djh83y@me.com',
                'phone' => '07771622623',
                'location' => 'Ba140ry',
                'vanType' => 'large-van',
                'services' => 'campervan-solar-panel-installation',
                'message' => 'Currently has no solar. It is a professionally converted 2017 LWB Sprinter',
            ],
            'created_at' => now()->subHour()->toIso8601String(),
            'site_url' => 'https://quantockvantech.com',
        ], $overrides);
    }

    public function test_preview_returns_unprocessed_count(): void
    {
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response([
                $this->mockSubmission(),
                $this->mockSubmission(['id' => 'sub_2']),
            ]),
        ]);

        $service = app(NetlifyFormService::class);
        $preview = $service->preview();

        $this->assertEquals(2, $preview['unprocessed']);
        $this->assertStringContainsString('2 unprocessed', $preview['message']);
    }

    public function test_sync_creates_customer_and_enquiry(): void
    {
        Mail::fake();
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response([
                $this->mockSubmission(),
            ]),
        ]);

        putenv('NOTIFICATION_EMAIL=admin@example.com');

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['errors']);

        $customer = Customer::where('email', 'djh83y@me.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('David Hornby', $customer->name);
        $this->assertEquals('07771622623', $customer->phone);
        $this->assertEquals('Ba140ry', $customer->address);

        $enquiry = Enquiry::where('customer_id', $customer->id)->first();
        $this->assertNotNull($enquiry);
        $this->assertEquals('web', $enquiry->source);
        $this->assertEquals('new', $enquiry->status);

        $this->assertStringContainsString('Currently has no solar', $enquiry->message);
            $this->assertStringContainsString('VanType: large-van', $enquiry->message);
        $this->assertStringContainsString('Services: campervan-solar-panel-installation', $enquiry->message);
    }

    public function test_sync_finds_existing_customer_by_email(): void
    {
        Mail::fake();
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        $existing = Customer::factory()->create([
            'email' => 'djh83y@me.com',
            'name' => 'Existing Name',
        ]);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response([
                $this->mockSubmission(),
            ]),
        ]);

        config(['services.netlify.notification_email' => 'admin@example.com']);

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(1, $result['processed']);

        $customer = Customer::find($existing->id);
        $this->assertEquals('Existing Name', $customer->name);
        $this->assertEquals(1, Customer::where('email', 'djh83y@me.com')->count());
    }

    public function test_sync_skips_already_processed_submissions(): void
    {
        Mail::fake();
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        $submission = $this->mockSubmission();

        ProcessedNetlifySubmission::create([
            'submission_id' => $submission['id'],
            'site_id' => self::SITE_ID,
            'submission_data' => $submission,
            'processed_at' => now(),
        ]);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response([$submission]),
        ]);

        config(['services.netlify.notification_email' => 'admin@example.com']);

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(1, $result['skipped']);

        Mail::assertNothingSent();
    }

    public function test_sync_nothing_sent_when_api_not_configured(): void
    {
        config(['services.netlify.site_id' => '']);
        config(['services.netlify.api_token' => '']);

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['errors']);
    }

    public function test_sync_handles_network_failure(): void
    {
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response(null, 500),
        ]);

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['errors']);
    }

    public function test_sync_handles_missing_customer_email_gracefully(): void
    {
        Mail::fake();
        config(['services.netlify.site_id' => self::SITE_ID]);
        config(['services.netlify.api_token' => 'test-token']);

        Http::fake([
            "https://api.netlify.com/api/v1/sites/*/submissions*" => Http::response([
                $this->mockSubmission([
                    'name' => 'No Email',
                    'email' => null,
                    'data' => [
                        'name' => 'No Email',
                        'phone' => '0777000000',
                        'location' => 'Somewhere',
                        'message' => 'Test message',
                    ],
                ]),
            ]),
        ]);

        config(['services.netlify.notification_email' => 'admin@example.com']);

        $service = app(NetlifyFormService::class);
        $result = $service->sync();

        $this->assertEquals(1, $result['processed']);

        $customer = Customer::where('name', 'No Email')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->email);
    }

    protected function tearDown(): void
    {
        putenv('NOTIFICATION_EMAIL');
        parent::tearDown();
    }
}
