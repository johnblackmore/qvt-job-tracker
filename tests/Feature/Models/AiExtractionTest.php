<?php

namespace Tests\Feature\Models;

use App\Models\AiExtraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_ai_extraction_record(): void
    {
        $user = User::factory()->create();

        $extraction = AiExtraction::create([
            'user_id' => $user->id,
            'assistant_name' => 'product-url-extractor',
            'source_url' => 'https://example.com/product/test',
            'status' => 'completed',
            'extracted_data' => ['name' => 'Test Product', 'sku' => 'TST-001'],
            'input_tokens' => 150,
            'output_tokens' => 50,
        ]);

        $this->assertDatabaseHas('ai_extractions', [
            'id' => $extraction->id,
            'user_id' => $user->id,
            'assistant_name' => 'product-url-extractor',
            'status' => 'completed',
            'input_tokens' => 150,
            'output_tokens' => 50,
        ]);
    }

    public function test_ai_extraction_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $extraction = AiExtraction::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $extraction->user);
        $this->assertEquals($user->id, $extraction->user->id);
    }
}
