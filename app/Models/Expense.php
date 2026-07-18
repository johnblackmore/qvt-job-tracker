<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number', 'expense_category_id', 'description', 'merchant_name',
        'total_amount', 'vat_total', 'expense_date', 'payment_method',
        'payment_reference', 'paid_at', 'status', 'bank_transaction_id',
        'notes', 'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ExpenseLineItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class, 'documentable_id')
            ->where('documentable_type', self::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
