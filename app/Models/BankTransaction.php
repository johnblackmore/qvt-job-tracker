<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
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
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
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
