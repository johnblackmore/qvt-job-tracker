<?php

namespace Tests\Feature\Livewire\Products;

use App\Livewire\Products\ProductForm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);
    }

    public function test_create_product_page_shows_create_from_url_button(): void
    {
        $component = Livewire::test(ProductForm::class);

        $component->assertSee('Create from URL');
    }

    public function test_edit_product_page_hides_create_from_url_button(): void
    {
        $product = Product::factory()->create();

        $component = Livewire::test(ProductForm::class, ['productId' => $product->id]);

        $component->assertDontSee('Create from URL');
    }

    public function test_open_url_modal_sets_show_property(): void
    {
        $component = Livewire::test(ProductForm::class);

        $component->assertSet('showUrlModal', false);

        $component->call('openUrlModal');

        $component->assertSet('showUrlModal', true);
        $component->assertSet('extractionUrl', '');
        $component->assertSet('extractedData', null);
        $component->assertSet('extractionError', null);
        $component->assertSet('isExtracting', false);
    }

    public function test_close_url_modal_resets_state(): void
    {
        $component = Livewire::test(ProductForm::class);

        $component->call('openUrlModal');
        $component->set('extractionUrl', 'https://example.com/product');
        $component->call('closeUrlModal');

        $component->assertSet('showUrlModal', false);
        $component->assertSet('extractionUrl', '');
        $component->assertSet('extractedData', null);
    }

    public function test_extract_from_url_validates_url(): void
    {
        $component = Livewire::test(ProductForm::class);

        $component->call('openUrlModal');
        $component->set('extractionUrl', 'not-a-url');
        $component->call('extractFromUrl');

        $component->assertHasErrors('extractionUrl');
    }

    public function test_apply_extracted_data_fills_form_fields(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Chargers']);
        $supplier = Supplier::factory()->create(['name' => 'Bimble Solar', 'is_active' => true]);

        $component = Livewire::test(ProductForm::class);

        $component->set('extractedData', [
            'name' => 'Victron SmartSolar MPPT 100/30',
            'sku' => 'SCC125075210',
            'description' => 'A solar charge controller',
            'retail_price' => 199.99,
            'category_name' => 'Chargers',
            'supplier_name' => 'Bimble Solar',
            'supplier_sku' => 'BIM-SCC125',
        ]);

        $component->call('applyExtractedData');

        $component->assertSet('name', 'Victron SmartSolar MPPT 100/30');
        $component->assertSet('sku', 'SCC125075210');
        $component->assertSet('description', 'A solar charge controller');
        $component->assertSet('retail_price', '199.99');
        $component->assertSet('category_id', $category->id);
        $component->assertSet('showUrlModal', false);

        $this->assertCount(1, $component->get('supplierLinks'));
        $this->assertEquals($supplier->id, $component->get('supplierLinks')[0]['supplier_id']);
        $this->assertEquals('BIM-SCC125', $component->get('supplierLinks')[0]['supplier_sku']);
        $this->assertTrue($component->get('supplierLinks')[0]['is_preferred']);
    }

    public function test_apply_extracted_data_without_supplier_match(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Batteries']);

        $component = Livewire::test(ProductForm::class);

        $component->set('extractedData', [
            'name' => 'Lithium Battery 100Ah',
            'sku' => 'LI-100',
            'description' => null,
            'retail_price' => 799.99,
            'category_name' => 'Batteries',
            'supplier_name' => 'Unknown Supplier Co',
            'supplier_sku' => null,
        ]);

        $component->call('applyExtractedData');

        $component->assertSet('name', 'Lithium Battery 100Ah');
        $component->assertSet('category_id', $category->id);

        $this->assertCount(1, $component->get('supplierLinks'));
        $this->assertEquals('', $component->get('supplierLinks')[0]['supplier_id']);
        $this->assertEquals('', $component->get('supplierLinks')[0]['supplier_sku']);
    }

    public function test_extracted_data_handles_partial_results(): void
    {
        $component = Livewire::test(ProductForm::class);

        $component->set('extractedData', [
            'name' => 'Just a Name',
            'sku' => null,
            'description' => null,
            'retail_price' => null,
            'category_name' => null,
            'supplier_name' => null,
            'supplier_sku' => null,
        ]);

        $component->call('applyExtractedData');

        $component->assertSet('name', 'Just a Name');
        $component->assertSet('sku', '');
        $component->assertSet('retail_price', '');
        $component->assertSet('category_id', null);
    }
}
