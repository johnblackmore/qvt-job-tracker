<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    public function toggleActive(int $id): void
    {
        $product = Product::find($id);
        if ($product) {
            $product->update(['is_active' => ! $product->is_active]);
        }
    }

    public function delete(int $id): void
    {
        Product::find($id)?->delete();
    }

    public function render()
    {
        $categories = ProductCategory::orderBy('name')->get();

        $products = Product::query()
            ->with(['category', 'suppliers'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('sku', 'like', "%{$this->search}%");
                });
            })
            ->when($this->category, function ($query) {
                $query->where('category_id', $this->category);
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.products.product-list', compact('products', 'categories'));
    }
}
