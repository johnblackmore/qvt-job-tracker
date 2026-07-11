<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductShow extends Component
{
    public Product $product;

    public function mount(int $id): void
    {
        $this->product = Product::with(['category', 'suppliers'])->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.products.product-show');
    }
}
