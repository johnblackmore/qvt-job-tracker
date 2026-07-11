<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'quote_id', 'reference_number', 'status',
        'total_amount', 'deposit_required', 'deposit_paid', 'balance_due',
        'scheduled_date', 'completed_at', 'staff_user_id', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'deposit_required' => 'decimal:2',
        'deposit_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function emailsSent(): HasMany
    {
        return $this->hasMany(EmailSent::class);
    }

    public function getDepositPercentAttribute(): float
    {
        if ($this->deposit_required <= 0) {
            return 0;
        }

        return round(($this->deposit_paid / $this->deposit_required) * 100, 1);
    }

    public function isFullyPaid(): bool
    {
        return $this->deposit_paid >= $this->deposit_required;
    }
}
