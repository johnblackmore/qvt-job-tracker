<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'provider_transaction_id',
        'amount',
        'currency',
        'description',
        'merchant_name',
        'merchant_category',
        'transaction_date',
        'settled_date',
        'is_pending',
        'is_load',
        'notes',
        'metadata',
        'expense_category',
        'reconciliation_status',
        'matched_payment_id',
        'imported_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'settled_date' => 'datetime',
        'is_pending' => 'boolean',
        'is_load' => 'boolean',
        'metadata' => 'array',
        'imported_at' => 'datetime',
        'matched_payment_id' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function reconciliationLink(): HasOne
    {
        return $this->hasOne(ReconciliationLink::class);
    }

    public static function expenseCategories(): array
    {
        return [
            'stock',
            'equipment',
            'travel',
            'fuel',
            'subsistence',
            'utilities',
            'professional_fees',
            'insurance',
        ];
    }

    public static function incomeCategories(): array
    {
        return [
            'customer_payment',
            'deposit',
            'transfer',
            'refund',
            'other_income',
        ];
    }

    public static function allCategories(): array
    {
        return array_merge(static::expenseCategories(), static::incomeCategories(), ['other']);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('reconciliation_status', 'unmatched');
    }

    public function scopeDebits($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('amount', '>', 0);
    }
}
