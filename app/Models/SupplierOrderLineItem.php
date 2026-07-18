<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierOrderLineItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_order_id', 'line_type', 'product_id', 'product_supplier_id',
        'supplier_sku', 'description', 'quantity', 'unit_amount', 'vat_rate',
        'vat_amount', 'line_total', 'line_type_category', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_amount' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(Allocation::class, 'allocatable_from');
    }
}
