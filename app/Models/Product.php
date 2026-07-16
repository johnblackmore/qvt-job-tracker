<?php

namespace App\Models;

use App\Services\VatService;
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
            ->withPivot(
                'trade_price', 'trade_price_includes_vat', 'vat_rate_type',
                'supplier_product_url', 'supplier_sku', 'is_preferred', 'lead_time_days', 'notes',
            )
            ->withTimestamps();
    }

    public function preferredSupplier(): ?Supplier
    {
        return $this->suppliers()
            ->wherePivot('is_preferred', true)
            ->first();
    }

    public function costPriceFromPivot(\stdClass|array $pivot): float
    {
        $tradePrice = (float) ($pivot->trade_price ?? $pivot['trade_price'] ?? 0);
        $includesVat = (bool) ($pivot->trade_price_includes_vat ?? $pivot['trade_price_includes_vat'] ?? false);
        $vatRateType = $pivot->vat_rate_type ?? $pivot['vat_rate_type'] ?? 'standard';

        return app(VatService::class)->calculateTrueCost($tradePrice, $includesVat, $vatRateType);
    }
}
