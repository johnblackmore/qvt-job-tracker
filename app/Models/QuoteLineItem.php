<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteLineItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_id', 'line_type', 'product_id', 'product_supplier_id',
        'description', 'quantity', 'unit_retail_price', 'unit_trade_price',
        'line_total_retail', 'line_total_trade', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_retail_price' => 'decimal:2',
        'unit_trade_price' => 'decimal:2',
        'line_total_retail' => 'decimal:2',
        'line_total_trade' => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
