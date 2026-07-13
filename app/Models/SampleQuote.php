<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SampleQuote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'line_items', 'is_active', 'notes'];

    protected $casts = [
        'line_items' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTotalAttribute(): float
    {
        return $this->retail_subtotal + $this->labour_total;
    }

    public function getRetailSubtotalAttribute(): float
    {
        $total = 0;

        foreach ($this->line_items ?? [] as $item) {
            if (($item['line_type'] ?? '') !== 'labour') {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_retail_price'] ?? 0);
                $total += $qty * $price;
            }
        }

        return $total;
    }

    public function getLabourTotalAttribute(): float
    {
        $total = 0;

        foreach ($this->line_items ?? [] as $item) {
            if (($item['line_type'] ?? '') === 'labour') {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_retail_price'] ?? 0);
                $total += $qty * $price;
            }
        }

        return $total;
    }
}
