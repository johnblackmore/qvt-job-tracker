<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\Ai\Assistants\ProductUrlAssistant;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;

    public string $sku = '';

    public string $name = '';

    public string $description = '';

    public ?int $category_id = null;

    public string $retail_price = '';

    public int $stock_qty = 0;

    public bool $is_active = true;

    public string $notes = '';

    public array $supplierLinks = [];

    public bool $showUrlModal = false;

    public string $extractionUrl = '';

    public ?array $extractedData = null;

    public bool $isExtracting = false;

    public ?string $extractionError = null;

    public function mount(?int $productId = null): void
    {
        if ($productId) {
            $this->product = Product::with('suppliers')->findOrFail($productId);
            $this->sku = $this->product->sku;
            $this->name = $this->product->name;
            $this->description = $this->product->description ?? '';
            $this->category_id = $this->product->category_id;
            $this->retail_price = (string) $this->product->retail_price;
            $this->stock_qty = $this->product->stock_qty;
            $this->is_active = $this->product->is_active;
            $this->notes = $this->product->notes ?? '';

            foreach ($this->product->suppliers as $supplier) {
                $this->supplierLinks[] = [
                    'supplier_id' => $supplier->id,
                    'trade_price' => (string) $supplier->pivot->trade_price,
                    'supplier_sku' => $supplier->pivot->supplier_sku ?? '',
                    'supplier_product_url' => $supplier->pivot->supplier_product_url ?? '',
                    'is_preferred' => (bool) $supplier->pivot->is_preferred,
                    'lead_time_days' => $supplier->pivot->lead_time_days ?? '',
                    'notes' => $supplier->pivot->notes ?? '',
                ];
            }
        }
    }

    public function addSupplierLink(): void
    {
        $this->supplierLinks[] = [
            'supplier_id' => '',
            'trade_price' => '',
            'supplier_sku' => '',
            'supplier_product_url' => '',
            'is_preferred' => false,
            'lead_time_days' => '',
            'notes' => '',
        ];
    }

    public function removeSupplierLink(int $index): void
    {
        unset($this->supplierLinks[$index]);
        $this->supplierLinks = array_values($this->supplierLinks);
    }

    public function openUrlModal(): void
    {
        $this->resetValidation('extractionUrl');
        $this->extractionUrl = '';
        $this->extractedData = null;
        $this->extractionError = null;
        $this->isExtracting = false;
        $this->showUrlModal = true;
    }

    public function closeUrlModal(): void
    {
        $this->showUrlModal = false;
        $this->extractionUrl = '';
        $this->extractedData = null;
        $this->extractionError = null;
        $this->isExtracting = false;
    }

    public function resetExtraction(): void
    {
        $this->extractedData = null;
        $this->extractionError = null;
        $this->isExtracting = false;
    }

    public function extractFromUrl(): void
    {
        $this->validate([
            'extractionUrl' => ['required', 'url', 'max:2048'],
        ]);

        $this->extractedData = null;
        $this->extractionError = null;
        $this->isExtracting = true;

        set_time_limit(120);

        try {
            $assistant = app(ProductUrlAssistant::class);
            $data = $assistant->extract($this->extractionUrl, auth()->user());

            $this->extractedData = $data;
        } catch (\Throwable $e) {
            $this->extractionError = $e->getMessage();
        } finally {
            $this->isExtracting = false;
        }
    }

    public function applyExtractedData(): void
    {
        if (! $this->extractedData) {
            return;
        }

        $data = $this->extractedData;

        if (! empty($data['name'])) {
            $this->name = $data['name'];
        }

        if (! empty($data['sku'])) {
            $this->sku = $data['sku'];
        }

        if (! empty($data['description'])) {
            $this->description = $data['description'];
        }

        if (! empty($data['retail_price'])) {
            $this->retail_price = (string) $data['retail_price'];
        }

        if (! empty($data['category_name'])) {
            $categories = ProductCategory::orderBy('name')->get();
            $matched = $categories->first(fn ($cat) => str_contains(
                mb_strtolower($cat->name),
                mb_strtolower($data['category_name'])
            ) || str_contains(
                mb_strtolower($data['category_name']),
                mb_strtolower($cat->name)
            ));

            if ($matched) {
                $this->category_id = $matched->id;
            }
        }

        $supplierLink = [
            'supplier_id' => '',
            'trade_price' => '',
            'supplier_sku' => $data['supplier_sku'] ?? '',
            'supplier_product_url' => $this->extractionUrl,
            'is_preferred' => true,
            'lead_time_days' => '',
            'notes' => '',
        ];

        if (! empty($data['supplier_name'])) {
            $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
            $matched = $suppliers->first(fn ($sup) => str_contains(
                mb_strtolower($sup->name),
                mb_strtolower($data['supplier_name'])
            ) || str_contains(
                mb_strtolower($data['supplier_name']),
                mb_strtolower($sup->name)
            ));

            if ($matched) {
                $supplierLink['supplier_id'] = $matched->id;
            }
        }

        $this->supplierLinks = [$supplierLink];

        $this->closeUrlModal();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sku' => ['required', 'string', 'max:255', $this->product ? 'unique:products,sku,'.$this->product->id : 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'supplierLinks' => ['nullable', 'array'],
            'supplierLinks.*.supplier_id' => ['required_with:supplierLinks', 'exists:suppliers,id'],
            'supplierLinks.*.trade_price' => ['required_with:supplierLinks', 'numeric', 'min:0'],
            'supplierLinks.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            'supplierLinks.*.supplier_product_url' => ['nullable', 'url', 'max:255'],
            'supplierLinks.*.is_preferred' => ['boolean'],
            'supplierLinks.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'supplierLinks.*.notes' => ['nullable', 'string'],
        ]);

        $productData = [
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'retail_price' => $validated['retail_price'],
            'stock_qty' => $validated['stock_qty'],
            'is_active' => $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($this->product) {
            $this->product->update($productData);
            $product = $this->product;
        } else {
            $product = Product::create($productData);
        }

        $syncData = [];
        foreach ($validated['supplierLinks'] ?? [] as $link) {
            $syncData[$link['supplier_id']] = [
                'trade_price' => $link['trade_price'],
                'supplier_sku' => $link['supplier_sku'] ?? null,
                'supplier_product_url' => $link['supplier_product_url'] ?? null,
                'is_preferred' => $link['is_preferred'] ?? false,
                'lead_time_days' => $link['lead_time_days'] ?? null,
                'notes' => $link['notes'] ?? null,
            ];
        }

        $preferredCount = collect($syncData)->where('is_preferred', true)->count();
        if ($preferredCount > 1) {
            $firstPreferred = true;
            foreach ($syncData as $supplierId => $data) {
                if ($data['is_preferred'] && $firstPreferred) {
                    $firstPreferred = false;
                } elseif ($data['is_preferred']) {
                    $syncData[$supplierId]['is_preferred'] = false;
                }
            }
        }

        $product->suppliers()->sync($syncData);

        $this->redirect(route('products.index'), navigate: true);
    }

    public function render()
    {
        $categories = ProductCategory::orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('livewire.products.product-form', compact('categories', 'suppliers'));
    }
}
