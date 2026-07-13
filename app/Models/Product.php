<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['sku', 'name', 'description', 'category_id', 'retail_price', 'stock_qty', 'is_active', 'notes'];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot('trade_price', 'supplier_product_url', 'supplier_sku', 'is_preferred', 'lead_time_days', 'notes')
            ->withTimestamps();
    }

    public function preferredSupplier(): ?Supplier
    {
        return $this->suppliers()
            ->wherePivot('is_preferred', true)
            ->first();
    }
}
