<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseLineItem extends Model
{
    protected $fillable = [
        'expense_id', 'description', 'line_type', 'amount', 'vat_rate',
        'vat_amount', 'line_type_category', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(Allocation::class, 'allocatable_from');
    }
}
