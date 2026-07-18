<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReconciliationLink extends Model
{
    protected $fillable = [
        'bank_transaction_id', 'reconcilable_type', 'reconcilable_id',
        'amount', 'matched_by_user_id', 'notes', 'matched_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function reconcilable(): MorphTo
    {
        return $this->morphTo();
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }
}
