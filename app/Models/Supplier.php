<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'contact_name', 'email', 'phone', 'website', 'address', 'notes', 'is_active',
        'default_trade_price_includes_vat',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_trade_price_includes_vat' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->withPivot('trade_price', 'supplier_product_url', 'supplier_sku', 'is_preferred', 'lead_time_days', 'notes')
            ->withTimestamps();
    }
}
