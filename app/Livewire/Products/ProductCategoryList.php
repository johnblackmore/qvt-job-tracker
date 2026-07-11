<?php

namespace App\Livewire\Products;

use App\Models\ProductCategory;
use Livewire\Component;
use Livewire\WithPagination;

class ProductCategoryList extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $id): void
    {
        ProductCategory::find($id)?->delete();
    }

    public function render()
    {
        $categories = ProductCategory::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.products.product-category-list', compact('categories'));
    }
}
