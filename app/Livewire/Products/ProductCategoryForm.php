<?php

namespace App\Livewire\Products;

use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductCategoryForm extends Component
{
    public ?ProductCategory $category = null;

    public string $name = '';
    public string $description = '';

    public function mount(?int $categoryId = null): void
    {
        if ($categoryId) {
            $this->category = ProductCategory::findOrFail($categoryId);
            $this->name = $this->category->name;
            $this->description = $this->category->description ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($this->category) {
            $this->category->update($validated);
        } else {
            ProductCategory::create($validated);
        }

        $this->redirect(route('products.categories.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.products.product-category-form');
    }
}
